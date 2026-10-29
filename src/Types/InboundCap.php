<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** The account's monthly cap on received mail, apart from the limits on sending, and how much of it is used. */
final readonly class InboundCap implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param int $limit the emails the account may receive in the calendar month in UTC, over every inbound stream. It is the account's monthly cap on sending until an administrator moves it in the dashboard
     * @param int $used the emails received this month so far, one per recipient
     * @param \DateTimeImmutable $resetsAt when the month ends and the count starts again, in UTC
     */
    public function __construct(
        public int $limit,
        public int $used,
        public \DateTimeImmutable $resetsAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            limit: Json::int($json, 'limit'),
            used: Json::int($json, 'used'),
            resetsAt: Json::time($json, 'resetsAt'),
            json: $json,
        );
    }
}
