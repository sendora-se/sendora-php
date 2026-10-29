<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * What came back for one attempt.
 *
 * @internal
 */
final class Reply
{
    /**
     * @param array<string, string> $headers the headers, their names lowercased
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        #[\SensitiveParameter]
        public readonly string $body,
    ) {}

    /**
     * Leaves out the body, which may hold a message's content.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['status' => $this->status, 'headers' => $this->headers];
    }
}
