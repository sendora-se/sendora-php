<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** Every inbound domain of the server. */
final readonly class InboundDomainList implements \JsonSerializable
{
    use KeepsJson;

    /** @param list<InboundDomain> $domains the domains */
    public function __construct(
        public array $domains,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            domains: \array_map(InboundDomain::fromWire(...), Json::objects($json, 'domains')),
            json: $json,
        );
    }
}
