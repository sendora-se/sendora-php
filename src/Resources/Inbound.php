<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\ApiStatusException;
use Sendora\Exception\ConflictException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Exception\UnprocessableEntityException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Pages;
use Sendora\Internal\Wire;
use Sendora\Types\InboundMessage;
use Sendora\Types\InboundMessageDetail;
use Sendora\Types\InboundPage;

/**
 * Searches and reads the mail that the server's inbound streams receive,
 * and downloads a message's raw bytes and its attachments.
 *
 * @final
 */
class Inbound
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * One page of received messages, newest first. Every filter is
     * optional. Pass the page's `next` as `after` for the following page,
     * or use `searchAll()`.
     *
     * ```php
     * $page = $sendora->inbound->search(from: 'anna@example.com', limit: 20);
     * ```
     *
     * @param null|string $streamId messages received on this inbound stream of the server
     * @param null|string $from messages from this address, in the envelope or the From header
     * @param null|string $recipient messages sent to this address of yours
     * @param null|string $mailboxHash messages whose address carried this text after the plus sign
     * @param null|\DateTimeInterface|string $receivedFrom messages accepted at or after this time, as a date or as RFC 3339 text
     * @param null|\DateTimeInterface|string $receivedTo messages accepted before this time, the same way
     * @param null|int $limit the page size, 1 to 100; 50 when left out
     * @param null|string $after the `next` of the previous page
     *
     * @throws UnprocessableEntityException no stream of this server has the id (`stream_not_found`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function search(
        ?string $streamId = null,
        #[\SensitiveParameter]
        ?string $from = null,
        #[\SensitiveParameter]
        ?string $recipient = null,
        #[\SensitiveParameter]
        ?string $mailboxHash = null,
        \DateTimeInterface|string|null $receivedFrom = null,
        \DateTimeInterface|string|null $receivedTo = null,
        ?int $limit = null,
        ?string $after = null,
    ): InboundPage {
        $body = Wire::body([
            'streamId' => $streamId,
            'from' => $from,
            'recipient' => $recipient,
            'mailboxHash' => $mailboxHash,
            'receivedFrom' => $receivedFrom,
            'receivedTo' => $receivedTo,
            'limit' => $limit,
            'after' => $after,
        ]);

        return $this->caller->json(
            new Call('POST', '/v1/inbound/search', idempotent: true, body: $body),
            InboundPage::fromWire(...),
        );
    }

    /**
     * Every received message the filters match, newest first, page by
     * page from `after` on. Each page is a request of its own, repeated on
     * its own when it fails. It takes the filters that `search()` takes.
     *
     * ```php
     * foreach ($sendora->inbound->searchAll(recipient: $address) as $message) {
     *     echo $message->receivedAt->format(DATE_ATOM), ' ', $message->subject, "\n";
     * }
     * ```
     *
     * @param null|string $streamId messages received on this inbound stream of the server
     * @param null|string $from messages from this address, in the envelope or the From header
     * @param null|string $recipient messages sent to this address of yours
     * @param null|string $mailboxHash messages whose address carried this text after the plus sign
     * @param null|\DateTimeInterface|string $receivedFrom messages accepted at or after this time, as a date or as RFC 3339 text
     * @param null|\DateTimeInterface|string $receivedTo messages accepted before this time, the same way
     * @param null|int $limit the page size, 1 to 100; 50 when left out
     * @param null|string $after the `next` of the page to start after
     *
     * @return \Generator<int, InboundMessage, mixed, void>
     *
     * @throws UnprocessableEntityException no stream of this server has the id (`stream_not_found`)
     * @throws SendoraException a page's call failed
     * @throws InvalidArgumentException an argument cannot be sent
     */
    public function searchAll(
        ?string $streamId = null,
        #[\SensitiveParameter]
        ?string $from = null,
        #[\SensitiveParameter]
        ?string $recipient = null,
        #[\SensitiveParameter]
        ?string $mailboxHash = null,
        \DateTimeInterface|string|null $receivedFrom = null,
        \DateTimeInterface|string|null $receivedTo = null,
        ?int $limit = null,
        ?string $after = null,
    ): \Generator {
        return Pages::all(function (?string $cursor) use ($streamId, $from, $recipient, $mailboxHash, $receivedFrom, $receivedTo, $limit): array {
            $page = $this->search(
                streamId: $streamId,
                from: $from,
                recipient: $recipient,
                mailboxHash: $mailboxHash,
                receivedFrom: $receivedFrom,
                receivedTo: $receivedTo,
                limit: $limit,
                after: $cursor,
            );

            return [$page->messages, $page->next];
        }, $after);
    }

    /**
     * One received message with everything but its attachments' bytes:
     * its envelope, parties, headers, text and HTML, and its attachments
     * described. Once the stream's content window has passed,
     * `contentAvailable` is `false`, and the content fields are `null` or
     * empty.
     *
     * ```php
     * $message = $sendora->inbound->get($inboundMessageId);
     * echo $message->subject, "\n", $message->text;
     * ```
     *
     * @throws NotFoundException no received message of this server has the id
     * @throws ConflictException its content cannot be opened (`content_unreadable`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $inboundMessageId): InboundMessageDetail
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/inbound/{}', $inboundMessageId), idempotent: true),
            InboundMessageDetail::fromWire(...),
        );
    }

    /**
     * The message byte for byte as it was received, as `message/rfc822`.
     * The whole message, up to the 35 MB a received message may be, is
     * returned at once.
     *
     * ```php
     * file_put_contents("{$inboundMessageId}.eml", $sendora->inbound->raw($inboundMessageId));
     * ```
     *
     * @throws NotFoundException no received message of this server has the id
     * @throws ApiStatusException the stream's content window has passed (`content_expired`, 410)
     * @throws ConflictException its content cannot be opened (`content_unreadable`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function raw(string $inboundMessageId): string
    {
        return $this->caller->bytes(new Call('GET', Wire::path('/v1/inbound/{}/raw', $inboundMessageId), idempotent: true, expect: Expect::Bytes));
    }

    /**
     * An attachment's bytes, whatever the sender declared them to be. Its
     * name and declared type are on the message. An empty attachment is an
     * empty string.
     *
     * ```php
     * $message = $sendora->inbound->get($inboundMessageId);
     * foreach ($message->attachments as $part) {
     *     $bytes = $sendora->inbound->attachment($inboundMessageId, $part->attachmentId);
     * }
     * ```
     *
     * @throws NotFoundException no received message of this server has the id, or it has no attachment with the other
     * @throws ApiStatusException the stream's content window has passed (`content_expired`, 410)
     * @throws ConflictException its content cannot be opened (`content_unreadable`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an id is empty, `.` or `..`; nothing was sent
     */
    public function attachment(string $inboundMessageId, string $attachmentId): string
    {
        return $this->caller->bytes(new Call(
            'GET',
            Wire::path('/v1/inbound/{}/attachments/{}', $inboundMessageId, $attachmentId),
            idempotent: true,
            expect: Expect::Bytes,
        ));
    }

    /**
     * Shows the API's address alone.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->caller->baseUrl];
    }
}
