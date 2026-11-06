<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Pages;
use Sendora\Internal\Wire;
use Sendora\Types\TrackingOptOut;
use Sendora\Types\TrackingOptOutPage;

/**
 * Reads the not-tracked list of the key's server, adds an address to it and
 * removes one.
 *
 * @final
 */
class TrackingOptOuts
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * One page of the server's not-tracked list, newest first. An entry
     * whose `address` is `null` was erased, and only a keyed hash of it
     * remains. Pass the page's `next` as `after` for the following page, or
     * use `listAll()`.
     *
     * ```php
     * $page = $sendora->trackingOptOuts->list(limit: 200);
     * ```
     *
     * @param null|int $limit the page size, 1 to 1000; 100 when left out
     * @param null|string $after the `next` of the previous page
     *
     * @throws SendoraException the call failed
     */
    public function list(?int $limit = null, ?string $after = null): TrackingOptOutPage
    {
        $query = \array_filter(['limit' => $limit, 'after' => $after], static fn(int|string|null $value): bool => $value !== null);

        return $this->caller->json(
            new Call('GET', '/v1/tracking-opt-outs', idempotent: true, query: $query),
            TrackingOptOutPage::fromWire(...),
        );
    }

    /**
     * Every entry of the list, page by page from `after` on. Each page is a
     * request of its own, repeated on its own when it fails.
     *
     * ```php
     * foreach ($sendora->trackingOptOuts->listAll() as $entry) {
     *     echo $entry->address ?? 'erased', ' ', $entry->source, "\n";
     * }
     * ```
     *
     * @param null|int $limit the page size, 1 to 1000; 100 when left out
     * @param null|string $after the `next` of the page to start after
     *
     * @return \Generator<int, TrackingOptOut, mixed, void>
     *
     * @throws SendoraException a page's call failed
     */
    public function listAll(?int $limit = null, ?string $after = null): \Generator
    {
        return Pages::all(function (?string $cursor) use ($limit): array {
            $page = $this->list(limit: $limit, after: $cursor);

            return [$page->trackingOptOuts, $page->next];
        }, $after);
    }

    /**
     * Puts an address on the server's list, so that it is never tracked, in
     * mail already sent too. An address already on the list changes nothing,
     * and the entry comes back as it stands.
     *
     * ```php
     * $entry = $sendora->trackingOptOuts->add(address: 'anna@example.com');
     * ```
     *
     * @param string $address the address never to track
     *
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function add(
        #[\SensitiveParameter]
        string $address,
    ): TrackingOptOut {
        $body = Wire::body(['address' => $address]);

        return $this->caller->json(
            new Call('POST', '/v1/tracking-opt-outs', idempotent: true, body: $body),
            TrackingOptOut::fromWire(...),
        );
    }

    /**
     * Takes an address off the server's list, so that its mail can be
     * tracked again. Remove an entry whose `source` is `recipient` only
     * after new consent. An erased entry is found by its address too. A
     * delete repeated after a lost answer whose first attempt took effect
     * throws `not_found`.
     *
     * ```php
     * $sendora->trackingOptOuts->delete(address: 'anna@example.com');
     * ```
     *
     * @param string $address the address to track again
     *
     * @throws NotFoundException the address is not on the list
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function delete(
        #[\SensitiveParameter]
        string $address,
    ): void {
        $body = Wire::body(['address' => $address]);

        $this->caller->nothing(new Call('POST', '/v1/tracking-opt-outs/delete', idempotent: true, body: $body, expect: Expect::Nothing));
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
