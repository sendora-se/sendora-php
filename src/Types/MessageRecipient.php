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
     * @param bool $tracked whether tracking applied to its copy when it left; it does not say to opens, links or both, or that the HTML held a link to track
     * @param int $opens the opens recorded, machines' included
     * @param int $clicks the clicks recorded, machines' included
     * @param null|\DateTimeImmutable $firstOpenAt the first open, or the first click that was likely a person's, in UTC; `null` before that
     * @param null|\DateTimeImmutable $firstHumanOpenAt the same, counting only what was likely a person, in UTC; `null` before that
     */
    public function __construct(
        public string $address,
        #[Values(RecipientKind::class)]
        public string $kind,
        #[Values(RecipientStatus::class)]
        public string $status,
        public bool $tracked,
        public int $opens,
        public int $clicks,
        public ?\DateTimeImmutable $firstOpenAt,
        public ?\DateTimeImmutable $firstHumanOpenAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            address: Json::string($json, 'address'),
            kind: Json::string($json, 'kind'),
            status: Json::string($json, 'status'),
            tracked: Json::bool($json, 'tracked'),
            opens: Json::int($json, 'opens'),
            clicks: Json::int($json, 'clicks'),
            firstOpenAt: Json::nullableTime($json, 'firstOpenAt'),
            firstHumanOpenAt: Json::nullableTime($json, 'firstHumanOpenAt'),
            json: $json,
        );
    }
}
