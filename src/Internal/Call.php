<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * One request, as a resource hands it to the transport.
 *
 * @internal
 */
final class Call
{
    /**
     * @param 'DELETE'|'GET'|'PATCH'|'POST' $method
     * @param string $path the path, its ids already encoded
     * @param bool $idempotent whether repeating the request after a lost answer can do nothing twice
     * @param array<string, int|string> $query the query, a left-out parameter absent
     * @param null|string $body the JSON body as text, or `null` to send none
     * @param null|string $idempotencyKey the key a send goes under, the same on every attempt
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly bool $idempotent,
        public readonly array $query = [],
        #[\SensitiveParameter]
        public readonly ?string $body = null,
        public readonly Expect $expect = Expect::Json,
        public readonly ?string $idempotencyKey = null,
    ) {}

    /**
     * Leaves out the body, which may hold a message's content.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'idempotent' => $this->idempotent,
            'query' => $this->query,
            'expect' => $this->expect,
            'idempotencyKey' => $this->idempotencyKey,
        ];
    }
}
