<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** The envelope that a received message came in. */
final readonly class InboundEnvelope implements \JsonSerializable
{
    use KeepsJson;

    /** @param null|string $sender the envelope sender; `null` for a bounce */
    public function __construct(
        public ?string $sender,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            sender: Json::nullableString($json, 'sender'),
            json: $json,
        );
    }
}
