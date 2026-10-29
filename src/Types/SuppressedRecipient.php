<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A recipient the server refused because it is on the stream's suppression list. */
final readonly class SuppressedRecipient implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $address the address refused
     * @param string $reason why it is suppressed; `SuppressionReason` lists the values this release knows, so a `match` over it needs a `default` arm
     */
    public function __construct(
        public string $address,
        #[Values(SuppressionReason::class)]
        public string $reason,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            address: Json::string($json, 'address'),
            reason: Json::string($json, 'reason'),
            json: $json,
        );
    }
}
