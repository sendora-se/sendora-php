<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A request to erase one person's data from every server of the account. */
final readonly class Erasure implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $erasureId the erasure's id
     * @param string $status `pending` until Sendora has erased the person, `done` after; `ErasureStatus` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $createdAt when it was asked for, in UTC
     * @param null|\DateTimeImmutable $completedAt when it was done; `null` while pending
     * @param null|string $pseudonym what now stands where the address was written in the message log; `null` while pending
     * @param null|int $messages the sent messages to the address whose content was deleted; `null` while pending
     * @param null|int $receivedMessages the received messages from, naming or to the address whose content was deleted; `null` while pending
     */
    public function __construct(
        public string $erasureId,
        #[Values(ErasureStatus::class)]
        public string $status,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $completedAt,
        public ?string $pseudonym,
        public ?int $messages,
        public ?int $receivedMessages,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            erasureId: Json::string($json, 'erasureId'),
            status: Json::string($json, 'status'),
            createdAt: Json::time($json, 'createdAt'),
            completedAt: Json::nullableTime($json, 'completedAt'),
            pseudonym: Json::nullableString($json, 'pseudonym'),
            messages: Json::nullableInt($json, 'messages'),
            receivedMessages: Json::nullableInt($json, 'receivedMessages'),
            json: $json,
        );
    }
}
