<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;

/** The recipient reported the message as spam; only Sendora's support lifts that suppression. */
final readonly class SpamComplaintEvent extends WebhookEvent
{
    /**
     * @param string $id the delivery's id; a delivery arrives at least once, so key your handling on it
     * @param int $attempt the attempt number, from 1
     * @param string $messageId the message it is about
     * @param string $recipient the recipient it is about
     * @param \DateTimeImmutable $occurredAt when it happened, in UTC
     * @param string $serverId the message's server
     * @param string $streamId the stream that the message went on
     * @param null|string $broadcastId the broadcast that the message belongs to; `null` for a message sent on its own
     * @param null|string $tag the label that the message was sent with
     * @param array<array-key, string> $metadata the pairs that the message was sent with
     * @param bool $test whether it is a test server's message, the event simulated and nothing sent
     * @param SpamComplaintDetails $details what the receiver reported
     */
    public function __construct(
        string $id,
        int $attempt,
        public string $messageId,
        public string $recipient,
        public \DateTimeImmutable $occurredAt,
        public string $serverId,
        public string $streamId,
        public ?string $broadcastId,
        public ?string $tag,
        public array $metadata,
        public bool $test,
        public SpamComplaintDetails $details,
        ?JsonObject $json = null,
    ) {
        parent::__construct($id, $attempt, 'spam_complaint', $json);
    }

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            id: Json::string($json, 'id'),
            attempt: Json::int($json, 'attempt'),
            messageId: Json::string($json, 'messageId'),
            recipient: Json::string($json, 'recipient'),
            occurredAt: Json::time($json, 'occurredAt'),
            serverId: Json::string($json, 'serverId'),
            streamId: Json::string($json, 'streamId'),
            broadcastId: Json::nullableString($json, 'broadcastId'),
            tag: Json::nullableString($json, 'tag'),
            metadata: Json::map($json, 'metadata'),
            test: Json::bool($json, 'test'),
            details: SpamComplaintDetails::fromWire(Json::object($json, 'details')),
            json: $json,
        );
    }
}
