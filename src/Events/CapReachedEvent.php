<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\Values;
use Sendora\Types\LimitScope;

/** A send was refused because a monthly cap on sending is used up. */
final readonly class CapReachedEvent extends WebhookEvent
{
    /**
     * @param string $id the delivery's id; a delivery arrives at least once, so key your handling on it
     * @param int $attempt the attempt number, from 1
     * @param string $scope whose cap it is; `LimitScope` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|string $serverId the server whose cap it is; `null` for the account's
     * @param int $cap the emails that the month allows
     * @param int $used the emails that the month has taken
     * @param \DateTimeImmutable $periodStart when the month began, in UTC
     * @param \DateTimeImmutable $periodEnd when it ends
     * @param \DateTimeImmutable $occurredAt when the event happened
     */
    public function __construct(
        string $id,
        int $attempt,
        #[Values(LimitScope::class)]
        public string $scope,
        public ?string $serverId,
        public int $cap,
        public int $used,
        public \DateTimeImmutable $periodStart,
        public \DateTimeImmutable $periodEnd,
        public \DateTimeImmutable $occurredAt,
        ?JsonObject $json = null,
    ) {
        parent::__construct($id, $attempt, 'cap_reached', $json);
    }

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            id: Json::string($json, 'id'),
            attempt: Json::int($json, 'attempt'),
            scope: Json::string($json, 'scope'),
            serverId: Json::nullableString($json, 'serverId'),
            cap: Json::int($json, 'cap'),
            used: Json::int($json, 'used'),
            periodStart: Json::time($json, 'periodStart'),
            periodEnd: Json::time($json, 'periodEnd'),
            occurredAt: Json::time($json, 'occurredAt'),
            json: $json,
        );
    }
}
