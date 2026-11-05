<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** Every tracking domain of the account. */
final readonly class TrackingDomainList implements \JsonSerializable
{
    use KeepsJson;

    /** @param list<TrackingDomain> $trackingDomains the tracking domains */
    public function __construct(
        public array $trackingDomains,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            trackingDomains: \array_map(TrackingDomain::fromWire(...), Json::objects($json, 'trackingDomains')),
            json: $json,
        );
    }
}
