<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** One limit on what the server sends, and how much of it is used. */
final readonly class ServerLimit implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $scope whose limit it is: the account's, the server's own, or the test cap that the account's test servers share. `LimitScope` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $period what it counts over: the UTC minute or the calendar month in UTC. `LimitPeriod` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param int $limit the emails allowed in the period, one per recipient
     * @param int $used the emails that the period has taken so far
     * @param \DateTimeImmutable $resetsAt when the period ends and the count starts again, in UTC
     */
    public function __construct(
        #[Values(LimitScope::class)]
        public string $scope,
        #[Values(LimitPeriod::class)]
        public string $period,
        public int $limit,
        public int $used,
        public \DateTimeImmutable $resetsAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            scope: Json::string($json, 'scope'),
            period: Json::string($json, 'period'),
            limit: Json::int($json, 'limit'),
            used: Json::int($json, 'used'),
            resetsAt: Json::time($json, 'resetsAt'),
            json: $json,
        );
    }
}
