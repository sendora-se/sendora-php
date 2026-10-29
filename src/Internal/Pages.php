<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * The twins of the paging methods: generators that follow `next` from the
 * caller's `after` until it is `null`. Each page is a request with its own
 * retries.
 *
 * @internal
 */
final class Pages
{
    /**
     * Every item of every page. Each is yielded without a key of its own, so
     * `iterator_to_array()` keeps them all rather than each page's keys
     * starting again at 0. The page's closure holds the search's filters,
     * an address among them, and `var_dump` of a trace shows what a closure
     * holds, so it is a sensitive parameter.
     *
     * @template T
     *
     * @param \Closure(?string): array{list<T>, ?string} $page the items after a cursor, and the cursor of the page after them
     *
     * @return \Generator<int, T, mixed, void>
     */
    public static function all(#[\SensitiveParameter] \Closure $page, ?string $after): \Generator
    {
        do {
            [$items, $after] = $page($after);
            foreach ($items as $item) {
                yield $item;
            }
        } while ($after !== null);
    }
}
