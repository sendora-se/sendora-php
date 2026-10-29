<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Pages;
use Sendora\Internal\Wire;
use Sendora\Types\Message;
use Sendora\Types\MessageDetail;
use Sendora\Types\MessagePage;

/**
 * Reads the message log of the key's server: one message with its
 * timeline, or a search.
 *
 * @final
 */
class Messages
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * One message as the log holds it, with its timeline: its recipients
     * and where each stands, its attachments described, and every event,
     * oldest first. Its bodies are never returned.
     *
     * ```php
     * $message = $sendora->messages->get($messageId);
     * foreach ($message->recipients as $recipient) {
     *     echo $recipient->address, ' ', $recipient->status, "\n";
     * }
     * ```
     *
     * @throws NotFoundException no message of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $messageId): MessageDetail
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/messages/{}', $messageId), idempotent: true),
            MessageDetail::fromWire(...),
        );
    }

    /**
     * One page of the log, newest first. Every filter is optional. Pass
     * the page's `next` as `after` for the following page, or use
     * `searchAll()`.
     *
     * ```php
     * $page = $sendora->messages->search(tag: 'invoice', status: RecipientStatus::Bounced->value);
     * ```
     *
     * @param null|string $recipient messages to this address, in `to`, `cc` or `bcc`. A whole address matches exactly. Anything else of three characters or more matches anywhere in an address, such as `kommun.se`.
     * @param null|string $streamId messages on this stream of the server
     * @param null|string $broadcastId messages of this broadcast
     * @param null|string $tag messages sent with this label
     * @param null|list<string>|string $status messages with at least one recipient in this state, or in any of a list of states; `RecipientStatus` lists them
     * @param null|\DateTimeInterface|string $from messages accepted at or after this time, as a date or as RFC 3339 text
     * @param null|\DateTimeInterface|string $to messages accepted before this time, the same way
     * @param null|int $limit the page size, 1 to 100; 50 when left out
     * @param null|string $after the `next` of the previous page
     *
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function search(
        #[\SensitiveParameter]
        ?string $recipient = null,
        ?string $streamId = null,
        ?string $broadcastId = null,
        ?string $tag = null,
        array|string|null $status = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $limit = null,
        ?string $after = null,
    ): MessagePage {
        $body = Wire::body([
            'recipient' => $recipient,
            'streamId' => $streamId,
            'broadcastId' => $broadcastId,
            'tag' => $tag,
            'status' => $status,
            'from' => $from,
            'to' => $to,
            'limit' => $limit,
            'after' => $after,
        ]);

        return $this->caller->json(
            new Call('POST', '/v1/messages/search', idempotent: true, body: $body),
            MessagePage::fromWire(...),
        );
    }

    /**
     * Every message the filters match, newest first, page by page from
     * `after` on. Each page is a request of its own, repeated on its own
     * when it fails. It takes the filters that `search()` takes.
     *
     * ```php
     * foreach ($sendora->messages->searchAll(tag: 'invoice') as $message) {
     *     echo $message->messageId, ' ', $message->subject, "\n";
     * }
     * ```
     *
     * @param null|string $recipient messages to this address, in `to`, `cc` or `bcc`. A whole address matches exactly. Anything else of three characters or more matches anywhere in an address, such as `kommun.se`.
     * @param null|string $streamId messages on this stream of the server
     * @param null|string $broadcastId messages of this broadcast
     * @param null|string $tag messages sent with this label
     * @param null|list<string>|string $status messages with at least one recipient in this state, or in any of a list of states; `RecipientStatus` lists them
     * @param null|\DateTimeInterface|string $from messages accepted at or after this time, as a date or as RFC 3339 text
     * @param null|\DateTimeInterface|string $to messages accepted before this time, the same way
     * @param null|int $limit the page size, 1 to 100; 50 when left out
     * @param null|string $after the `next` of the page to start after
     *
     * @return \Generator<int, Message, mixed, void>
     *
     * @throws SendoraException a page's call failed
     * @throws InvalidArgumentException an argument cannot be sent
     */
    public function searchAll(
        #[\SensitiveParameter]
        ?string $recipient = null,
        ?string $streamId = null,
        ?string $broadcastId = null,
        ?string $tag = null,
        array|string|null $status = null,
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?int $limit = null,
        ?string $after = null,
    ): \Generator {
        return Pages::all(function (?string $cursor) use ($recipient, $streamId, $broadcastId, $tag, $status, $from, $to, $limit): array {
            $page = $this->search(
                recipient: $recipient,
                streamId: $streamId,
                broadcastId: $broadcastId,
                tag: $tag,
                status: $status,
                from: $from,
                to: $to,
                limit: $limit,
                after: $cursor,
            );

            return [$page->messages, $page->next];
        }, $after);
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
