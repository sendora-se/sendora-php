<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One page of a stream's suppression list. */
final readonly class SuppressionPage implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param list<Suppression> $suppressions the page's entries
     * @param null|string $next pass as `after` for the next page; `null` on the last
     */
    public function __construct(
        public array $suppressions,
        public ?string $next,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            suppressions: \array_map(Suppression::fromWire(...), Json::objects($json, 'suppressions')),
            next: Json::nullableString($json, 'next'),
            json: $json,
        );
    }
}
