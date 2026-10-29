<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A broadcast stored. The worker sends its messages from here. */
final readonly class AcceptedBroadcast implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $broadcastId the id its messages, its progress and a cancel refer to
     * @param string $status `accepted`
     * @param int $total the messages stored, each a message in the log
     * @param int $suppressed the addresses dropped for standing on the stream's suppression list
     * @param \DateTimeImmutable $submittedAt when it was accepted, in UTC
     * @param bool $test whether the server is a test server, whose mail reaches nobody
     */
    public function __construct(
        public string $broadcastId,
        public string $status,
        public int $total,
        public int $suppressed,
        public \DateTimeImmutable $submittedAt,
        public bool $test,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            broadcastId: Json::string($json, 'broadcastId'),
            status: Json::string($json, 'status'),
            total: Json::int($json, 'total'),
            suppressed: Json::int($json, 'suppressed'),
            submittedAt: Json::time($json, 'submittedAt'),
            test: Json::bool($json, 'test'),
            json: $json,
        );
    }
}
