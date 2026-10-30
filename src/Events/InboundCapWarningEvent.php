<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;

/** 80 percent of the account's monthly cap on received mail is used. */
final readonly class InboundCapWarningEvent extends WebhookEvent
{
    /**
     * @param string $id the delivery's id; a delivery arrives at least once, so key your handling on it
     * @param int $attempt the attempt number, from 1
     * @param int $cap the emails that the account may receive in the month
     * @param int $used the emails received in the month so far
     * @param \DateTimeImmutable $periodStart when the month began, in UTC
     * @param \DateTimeImmutable $periodEnd when it ends and received mail is counted from 0 again
     * @param \DateTimeImmutable $occurredAt when the event happened
     */
    public function __construct(
        string $id,
        int $attempt,
        public int $cap,
        public int $used,
        public \DateTimeImmutable $periodStart,
        public \DateTimeImmutable $periodEnd,
        public \DateTimeImmutable $occurredAt,
        ?JsonObject $json = null,
    ) {
        parent::__construct($id, $attempt, 'inbound_cap_warning', $json);
    }

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            id: Json::string($json, 'id'),
            attempt: Json::int($json, 'attempt'),
            cap: Json::int($json, 'cap'),
            used: Json::int($json, 'used'),
            periodStart: Json::time($json, 'periodStart'),
            periodEnd: Json::time($json, 'periodEnd'),
            occurredAt: Json::time($json, 'occurredAt'),
            json: $json,
        );
    }
}
