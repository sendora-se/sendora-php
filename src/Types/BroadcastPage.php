<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One page of the server's broadcasts, newest first. */
final readonly class BroadcastPage implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param list<Broadcast> $broadcasts the page's broadcasts
     * @param null|string $next pass as `after` for the next page; `null` on the last
     */
    public function __construct(
        public array $broadcasts,
        public ?string $next,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            broadcasts: \array_map(Broadcast::fromWire(...), Json::objects($json, 'broadcasts')),
            next: Json::nullableString($json, 'next'),
            json: $json,
        );
    }
}
