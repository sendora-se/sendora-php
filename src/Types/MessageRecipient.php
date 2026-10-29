<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A recipient of a message, and where its delivery stands. */
final readonly class MessageRecipient implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $address the address
     * @param string $kind whether it was in `to`, `cc` or `bcc`; `RecipientKind` lists the values
     * @param string $status where its delivery stands; `RecipientStatus` lists the values this release knows, so a `match` over it needs a `default` arm
     */
    public function __construct(
        public string $address,
        #[Values(RecipientKind::class)]
        public string $kind,
        #[Values(RecipientStatus::class)]
        public string $status,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            address: Json::string($json, 'address'),
            kind: Json::string($json, 'kind'),
            status: Json::string($json, 'status'),
            json: $json,
        );
    }
}
