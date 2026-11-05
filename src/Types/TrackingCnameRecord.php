<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** The CNAME record that a tracking domain needs, and what the last check found. */
final readonly class TrackingCnameRecord implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $type `CNAME`
     * @param string $host the name to create it under
     * @param string $value the value it must hold
     * @param null|string $check what the last check found, null before the first; `TrackingCnameCheck` lists the values this release knows, so a `match` over it needs a `default` arm
     */
    public function __construct(
        public string $type,
        public string $host,
        public string $value,
        #[Values(TrackingCnameCheck::class)]
        public ?string $check,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            type: Json::string($json, 'type'),
            host: Json::string($json, 'host'),
            value: Json::string($json, 'value'),
            check: Json::nullableString($json, 'check'),
            json: $json,
        );
    }
}
