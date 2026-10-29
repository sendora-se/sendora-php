<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** An address of a received message, with its display name. */
final readonly class InboundAddress implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $address the address
     * @param null|string $name the display name, when it has one
     */
    public function __construct(
        public string $address,
        public ?string $name,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            address: Json::string($json, 'address'),
            name: Json::nullableString($json, 'name'),
            json: $json,
        );
    }
}
