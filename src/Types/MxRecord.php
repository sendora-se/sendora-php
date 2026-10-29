<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** The MX record that a domain needs, and whether it has been seen. */
final readonly class MxRecord implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $type `MX`
     * @param string $host the name to create it under
     * @param string $value the value it must hold, at any priority
     * @param bool $verified whether it has been seen
     * @param null|\DateTimeImmutable $verifiedAt when it was first seen
     */
    public function __construct(
        public string $type,
        public string $host,
        public string $value,
        public bool $verified,
        public ?\DateTimeImmutable $verifiedAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            type: Json::string($json, 'type'),
            host: Json::string($json, 'host'),
            value: Json::string($json, 'value'),
            verified: Json::bool($json, 'verified'),
            verifiedAt: Json::nullableTime($json, 'verifiedAt'),
            json: $json,
        );
    }
}
