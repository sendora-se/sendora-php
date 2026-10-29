<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One header of a received message, its name as received. */
final readonly class InboundHeader implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $name the header's name
     * @param string $value its value
     */
    public function __construct(
        public string $name,
        public string $value,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            name: Json::string($json, 'name'),
            value: Json::string($json, 'value'),
            json: $json,
        );
    }
}
