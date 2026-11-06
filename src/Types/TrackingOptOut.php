<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** An address that the server never tracks. */
final readonly class TrackingOptOut implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param null|string $address the address, in lower case; `null` once an erasure has left only a keyed hash
     * @param string $source `manual` if added through the API or the dashboard, `recipient` if the recipient asked for it; remove a recipient's own entry only after new consent. `TrackingOptOutSource` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|string $messageId the message that a recipient asked from; `null` on a manual entry, and once that message or the address has gone
     * @param \DateTimeImmutable $createdAt when it was added, in UTC
     */
    public function __construct(
        public ?string $address,
        #[Values(TrackingOptOutSource::class)]
        public string $source,
        public ?string $messageId,
        public \DateTimeImmutable $createdAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            address: Json::nullableString($json, 'address'),
            source: Json::string($json, 'source'),
            messageId: Json::nullableString($json, 'messageId'),
            createdAt: Json::time($json, 'createdAt'),
            json: $json,
        );
    }
}
