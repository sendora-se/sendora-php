<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One page of a server's not-tracked list, newest first. */
final readonly class TrackingOptOutPage implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param list<TrackingOptOut> $trackingOptOuts the page's entries
     * @param null|string $next pass as `after` for the next page; `null` on the last
     */
    public function __construct(
        public array $trackingOptOuts,
        public ?string $next,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            trackingOptOuts: \array_map(TrackingOptOut::fromWire(...), Json::objects($json, 'trackingOptOuts')),
            next: Json::nullableString($json, 'next'),
            json: $json,
        );
    }
}
