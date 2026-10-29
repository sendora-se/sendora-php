<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A sending domain of the account that a From address may use now. */
final readonly class SendableDomain implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $domain the domain, as the part of a From address after the @
     * @param null|\DateTimeImmutable $failingSince when a check first found one of its records missing or wrong; mail keeps going out for 24 hours from then. `null` while every record is in place
     */
    public function __construct(
        public string $domain,
        public ?\DateTimeImmutable $failingSince,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            domain: Json::string($json, 'domain'),
            failingSince: Json::nullableTime($json, 'failingSince'),
            json: $json,
        );
    }
}
