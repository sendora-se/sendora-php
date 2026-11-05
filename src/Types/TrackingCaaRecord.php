<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** The CAA record that lets a tracking domain's certificate be issued. */
final readonly class TrackingCaaRecord implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $type `CAA`
     * @param string $host the name that holds the CAA records in the way
     * @param string $value the record to add beside them
     */
    public function __construct(
        public string $type,
        public string $host,
        public string $value,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            type: Json::string($json, 'type'),
            host: Json::string($json, 'host'),
            value: Json::string($json, 'value'),
            json: $json,
        );
    }
}
