<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** Every sending domain of the account. */
final readonly class DomainList implements \JsonSerializable
{
    use KeepsJson;

    /** @param list<SendingDomain> $domains the domains */
    public function __construct(
        public array $domains,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            domains: \array_map(SendingDomain::fromWire(...), Json::objects($json, 'domains')),
            json: $json,
        );
    }
}
