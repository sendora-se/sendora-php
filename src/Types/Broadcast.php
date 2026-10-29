<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A broadcast and how far it has come. */
final readonly class Broadcast implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $broadcastId the broadcast's id
     * @param string $streamId the broadcast stream its messages go on
     * @param string $status where it stands; `BroadcastStatus` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $from the sender's address
     * @param string $subject the subject line
     * @param null|string $tag the label it was sent with
     * @param array<array-key, string> $metadata the pairs it was sent with, their keys as they were sent
     * @param int $total the messages stored, after suppressed addresses were dropped
     * @param int $released the messages handed to the mail server so far
     * @param int $failed the messages that could not be handed over, cancelled ones included
     * @param int $suppressed the addresses dropped for standing on the stream's suppression list
     * @param \DateTimeImmutable $submittedAt when it was accepted, in UTC
     * @param null|\DateTimeImmutable $completedAt when nothing was left to send, or it was cancelled
     */
    public function __construct(
        public string $broadcastId,
        public string $streamId,
        #[Values(BroadcastStatus::class)]
        public string $status,
        public string $from,
        public string $subject,
        public ?string $tag,
        public array $metadata,
        public int $total,
        public int $released,
        public int $failed,
        public int $suppressed,
        public \DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $completedAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            broadcastId: Json::string($json, 'broadcastId'),
            streamId: Json::string($json, 'streamId'),
            status: Json::string($json, 'status'),
            from: Json::string($json, 'from'),
            subject: Json::string($json, 'subject'),
            tag: Json::nullableString($json, 'tag'),
            metadata: Json::map($json, 'metadata'),
            total: Json::int($json, 'total'),
            released: Json::int($json, 'released'),
            failed: Json::int($json, 'failed'),
            suppressed: Json::int($json, 'suppressed'),
            submittedAt: Json::time($json, 'submittedAt'),
            completedAt: Json::nullableTime($json, 'completedAt'),
            json: $json,
        );
    }
}
