<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** An address that a stream of the server no longer sends to. */
final readonly class Suppression implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $streamId the stream whose list holds it
     * @param string $address the address, in lower case
     * @param string $reason why it is there; a hard bounce or a manual entry may be lifted, a spam complaint or an unsubscribe only by Sendora's support. `SuppressionReason` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|string $messageId the message whose bounce or complaint put it there
     * @param \DateTimeImmutable $createdAt when it was added, in UTC
     */
    public function __construct(
        public string $streamId,
        public string $address,
        #[Values(SuppressionReason::class)]
        public string $reason,
        public ?string $messageId,
        public \DateTimeImmutable $createdAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            streamId: Json::string($json, 'streamId'),
            address: Json::string($json, 'address'),
            reason: Json::string($json, 'reason'),
            messageId: Json::nullableString($json, 'messageId'),
            createdAt: Json::time($json, 'createdAt'),
            json: $json,
        );
    }
}
