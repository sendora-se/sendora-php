<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** Why a message bounced. */
final readonly class BouncedDetails implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param null|int $code the SMTP status that the receiver answered
     * @param null|string $response its response text
     * @param null|string $classification how the bounce was classified, such as `InvalidRecipient`
     * @param bool $hard whether the address is now on the suppression list
     * @param bool $expired whether the message expired after repeated deferrals rather than being refused
     */
    public function __construct(
        public ?int $code,
        public ?string $response,
        public ?string $classification,
        public bool $hard,
        public bool $expired,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            code: Json::nullableInt($json, 'code'),
            response: Json::nullableString($json, 'response'),
            classification: Json::nullableString($json, 'classification'),
            hard: Json::bool($json, 'hard'),
            expired: Json::bool($json, 'expired'),
            json: $json,
        );
    }
}
