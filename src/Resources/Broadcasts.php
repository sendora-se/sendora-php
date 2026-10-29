<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Attachment;
use Sendora\Exception\ConflictException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Idempotency;
use Sendora\Internal\Wire;
use Sendora\Types\AcceptedBroadcast;
use Sendora\Types\Broadcast;
use Sendora\Types\BroadcastPage;

/**
 * Sends, follows and cancels broadcasts on the server's broadcast streams.
 *
 * `BroadcastMessage` is one message of a broadcast as an array: its
 * recipients and what is its own. Its `metadata` and `headers` are merged
 * over the broadcast's. Its `substitutions`, up to 20, replace
 * `{{ key }}` in the subject, the text and the HTML of this message, and
 * a key the content names must be given. Import it with
 * `@phpstan-import-type BroadcastMessage from Broadcasts`.
 *
 * @phpstan-type Address string|array{email: string, name?: null|string}
 * @phpstan-type BroadcastAttachment Attachment|array{name: string, content: string, contentType?: null|string, contentId?: null|string}
 * @phpstan-type BroadcastMessage array{
 *     to: list<Address>,
 *     cc?: null|list<Address>,
 *     bcc?: null|list<Address>,
 *     metadata?: null|array<array-key, string>,
 *     headers?: null|array<array-key, string>,
 *     substitutions?: null|array<array-key, string>,
 * }
 *
 * @final
 */
class Broadcasts
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Stores the content once and a message per entry, up to 50,000.
     * Every message is a message in the log with its own events and
     * webhooks, sent with the recipient's own unsubscribe link where
     * `{{ unsubscribe_url }}` stands. Addresses on the stream's suppression
     * list are dropped and counted in `suppressed`, and a list with nothing
     * left throws `recipient_suppressed`. Every broadcast goes under an
     * idempotency key, yours or a random one, so a retry answers the same
     * broadcast.
     *
     * At least one of `text` and `html` is needed, each with
     * `{{ unsubscribe_url }}` where the link goes. The API refuses one
     * without.
     *
     * ```php
     * $accepted = $sendora->broadcasts->send(
     *     streamId: $streamId,
     *     from: ['email' => 'nyheter@example.se', 'name' => 'Example AB'],
     *     subject: 'Nyheter i oktober',
     *     messages: array_map(fn (string $address): array => ['to' => [$address]], $recipients),
     *     text: 'Månadens nyheter. Avsluta: {{ unsubscribe_url }}',
     * );
     * echo $accepted->total, ' stored, ', $accepted->suppressed, " suppressed\n";
     * ```
     *
     * @param string $streamId the broadcast stream of this server the messages go on
     * @param Address $from the sender, whose domain must be a verified sending domain of the account
     * @param string $subject the subject line, 1 to 998 characters
     * @param list<BroadcastMessage> $messages one entry per message, at most 50,000; a larger list is several broadcasts under one tag
     * @param null|string $text the plain-text part
     * @param null|string $html the HTML part
     * @param null|array<array-key, string> $headers custom headers on every message, sent as given; a header that Sendora sets itself, the List-Unsubscribe pair included, is refused
     * @param null|list<BroadcastAttachment> $attachments at most 20, sent with every message; the whole broadcast may not exceed 50 MB once built
     * @param null|string $tag a label of up to 100 characters, on the broadcast and every message
     * @param null|array<array-key, string> $metadata up to 20 pairs of your own, on the broadcast and every message, their keys sent as given
     * @param null|string $idempotencyKey 1 to 255 characters naming this broadcast, none of them a control character or beyond U+00FF. The API keeps a key for 24 hours. Give your own to repeat a broadcast safely from another process.
     *
     * @throws SendoraException the broadcast was refused or failed; its `idempotencyKey` is the key it went under, for repeating it later
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function send(
        string $streamId,
        #[\SensitiveParameter]
        array|string $from,
        #[\SensitiveParameter]
        string $subject,
        #[\SensitiveParameter]
        array $messages,
        #[\SensitiveParameter]
        ?string $text = null,
        #[\SensitiveParameter]
        ?string $html = null,
        #[\SensitiveParameter]
        ?array $headers = null,
        #[\SensitiveParameter]
        ?array $attachments = null,
        ?string $tag = null,
        #[\SensitiveParameter]
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): AcceptedBroadcast {
        $key = Idempotency::keyFor($idempotencyKey);
        $body = Wire::body([
            'streamId' => $streamId,
            'from' => $from,
            'subject' => $subject,
            'messages' => $messages,
            'text' => $text,
            'html' => $html,
            'headers' => $headers,
            'attachments' => $attachments,
            'tag' => $tag,
            'metadata' => $metadata,
        ]);

        return $this->caller->json(
            new Call('POST', '/v1/broadcasts', idempotent: true, body: $body, idempotencyKey: $key),
            AcceptedBroadcast::fromWire(...),
        );
    }

    /**
     * One page of the server's broadcasts, newest first. Pass the page's
     * `next` as `after` for the following page.
     *
     * ```php
     * $page = $sendora->broadcasts->list(limit: 20);
     * ```
     *
     * @param null|int $limit the page size, 1 to 100; 50 when left out
     * @param null|string $after the `next` of the previous page
     *
     * @throws SendoraException the call failed
     */
    public function list(?int $limit = null, ?string $after = null): BroadcastPage
    {
        $query = \array_filter(['limit' => $limit, 'after' => $after], static fn(int|string|null $value): bool => $value !== null);

        return $this->caller->json(
            new Call('GET', '/v1/broadcasts', idempotent: true, query: $query),
            BroadcastPage::fromWire(...),
        );
    }

    /**
     * A broadcast and how far it has come. `released` and `failed` move as
     * the worker hands its messages over, and `status` is `completed` once
     * nothing is left.
     *
     * ```php
     * $broadcast = $sendora->broadcasts->get($broadcastId);
     * echo $broadcast->released, ' of ', $broadcast->total, "\n";
     * ```
     *
     * @throws NotFoundException no broadcast of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $broadcastId): Broadcast
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/broadcasts/{}', $broadcastId), idempotent: true),
            Broadcast::fromWire(...),
        );
    }

    /**
     * Holds every message not yet handed to the mail server. Those count
     * as `failed`, and a message already on its way is delivered. A cancel
     * repeated after a lost answer, whose first attempt took effect,
     * throws `broadcast_not_open`, as a completed or cancelled broadcast
     * does.
     *
     * ```php
     * $cancelled = $sendora->broadcasts->cancel($broadcastId);
     * echo $cancelled->failed, " messages held back\n";
     * ```
     *
     * @throws ConflictException the broadcast is completed or cancelled (`broadcast_not_open`)
     * @throws NotFoundException no broadcast of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function cancel(string $broadcastId): Broadcast
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/broadcasts/{}/cancel', $broadcastId), idempotent: true),
            Broadcast::fromWire(...),
        );
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
