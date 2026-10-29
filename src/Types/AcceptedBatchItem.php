<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A message of a batch that was accepted. */
final readonly class AcceptedBatchItem implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $messageId the id that the message log, its events and the webhooks refer to
     * @param string $status `accepted`
     * @param \DateTimeImmutable $submittedAt when the message was accepted, in UTC
     * @param bool $test whether the server is a test server
     * @param int $index the message's position in the batch
     * @param bool $replayed whether an earlier batch under the same idempotency key already sent it
     */
    public function __construct(
        public string $messageId,
        public string $status,
        public \DateTimeImmutable $submittedAt,
        public bool $test,
        public int $index,
        public bool $replayed,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            messageId: Json::string($json, 'messageId'),
            status: Json::string($json, 'status'),
            submittedAt: Json::time($json, 'submittedAt'),
            test: Json::bool($json, 'test'),
            index: Json::int($json, 'index'),
            replayed: Json::bool($json, 'replayed'),
            json: $json,
        );
    }
}
