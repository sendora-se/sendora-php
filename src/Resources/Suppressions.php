<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\PermissionDeniedException;
use Sendora\Exception\SendoraException;
use Sendora\Exception\UnprocessableEntityException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Pages;
use Sendora\Internal\Wire;
use Sendora\Types\Suppression;
use Sendora\Types\SuppressionPage;

/**
 * Reads the suppression lists of the key's server, one list per stream,
 * and lifts an entry from one.
 *
 * @final
 */
class Suppressions
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * One page of a stream's suppression list: its hard bounces, spam
     * complaints, unsubscribes and entries made by hand. A send to any of
     * them is refused with `recipient_suppressed`. Pass the page's `next`
     * as `after` for the following page, or use `listAll()`.
     *
     * ```php
     * $page = $sendora->suppressions->list(streamId: $streamId, limit: 200);
     * ```
     *
     * @param null|string $streamId the stream whose list is meant; the default transactional stream when left out
     * @param null|int $limit the page size, 1 to 1000; 100 when left out
     * @param null|string $after the `next` of the previous page
     *
     * @throws UnprocessableEntityException no stream of this server has the id (`stream_not_found`)
     * @throws SendoraException the call failed
     */
    public function list(?string $streamId = null, ?int $limit = null, ?string $after = null): SuppressionPage
    {
        $query = \array_filter(['streamId' => $streamId, 'limit' => $limit, 'after' => $after], static fn(int|string|null $value): bool => $value !== null);

        return $this->caller->json(
            new Call('GET', '/v1/suppressions', idempotent: true, query: $query),
            SuppressionPage::fromWire(...),
        );
    }

    /**
     * Every entry of a stream's list, page by page from `after` on. Each
     * page is a request of its own, repeated on its own when it fails.
     *
     * ```php
     * foreach ($sendora->suppressions->listAll() as $entry) {
     *     echo $entry->address, ' ', $entry->reason, "\n";
     * }
     * ```
     *
     * @param null|string $streamId the stream whose list is meant; the default transactional stream when left out
     * @param null|int $limit the page size, 1 to 1000; 100 when left out
     * @param null|string $after the `next` of the page to start after
     *
     * @return \Generator<int, Suppression, mixed, void>
     *
     * @throws UnprocessableEntityException no stream of this server has the id (`stream_not_found`)
     * @throws SendoraException a page's call failed
     */
    public function listAll(?string $streamId = null, ?int $limit = null, ?string $after = null): \Generator
    {
        return Pages::all(function (?string $cursor) use ($streamId, $limit): array {
            $page = $this->list(streamId: $streamId, limit: $limit, after: $cursor);

            return [$page->suppressions, $page->next];
        }, $after);
    }

    /**
     * Lifts a hard bounce or an entry made by hand from a stream's list, so
     * that the server may send to the address on that stream again. Only
     * Sendora's support lifts a spam complaint or an unsubscribe. A delete
     * repeated after a lost answer whose first attempt took effect throws
     * `not_found`.
     *
     * ```php
     * $sendora->suppressions->delete(address: 'anna@example.com');
     * ```
     *
     * @param string $address the address to send to again
     * @param null|string $streamId the stream whose list holds it; the default transactional stream when left out
     *
     * @throws NotFoundException the address is not on the list
     * @throws PermissionDeniedException the entry is a spam complaint or an unsubscribe (`spam_complaint_locked`, `unsubscribe_locked`)
     * @throws UnprocessableEntityException no stream of this server has the id (`stream_not_found`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function delete(
        #[\SensitiveParameter]
        string $address,
        ?string $streamId = null,
    ): void {
        $body = Wire::body(['address' => $address, 'streamId' => $streamId]);

        $this->caller->nothing(new Call('POST', '/v1/suppressions/delete', idempotent: true, body: $body, expect: Expect::Nothing));
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
