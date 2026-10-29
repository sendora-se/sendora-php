<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One page of a webhook's deliveries, newest first. */
final readonly class DeliveryPage implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param list<Delivery> $deliveries the page's deliveries
     * @param null|string $next pass as `after` for the next page; `null` on the last
     */
    public function __construct(
        public array $deliveries,
        public ?string $next,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            deliveries: \array_map(Delivery::fromWire(...), Json::objects($json, 'deliveries')),
            next: Json::nullableString($json, 'next'),
            json: $json,
        );
    }
}
