<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** One step of a message's timeline. */
final readonly class MessageEvent implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param null|string $recipient the recipient it is about; `null` when it concerns the message as a whole
     * @param string $type what happened; `MessageEventType` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $at when, in UTC
     * @param null|int $code the SMTP status the receiver answered
     * @param null|string $details the receiver's response text
     * @param null|string $classification how a bounce was classified, such as `InvalidRecipient`
     */
    public function __construct(
        public ?string $recipient,
        #[Values(MessageEventType::class)]
        public string $type,
        public \DateTimeImmutable $at,
        public ?int $code,
        public ?string $details,
        public ?string $classification,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            recipient: Json::nullableString($json, 'recipient'),
            type: Json::string($json, 'type'),
            at: Json::time($json, 'at'),
            code: Json::nullableInt($json, 'code'),
            details: Json::nullableString($json, 'details'),
            classification: Json::nullableString($json, 'classification'),
            json: $json,
        );
    }
}
