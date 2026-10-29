<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/**
 * A message with its attachments described and its timeline: what
 * `Message` has, and two lists more. Its bodies are never returned.
 */
final readonly class MessageDetail implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $messageId the message's id
     * @param string $streamId the stream it went on
     * @param null|string $broadcastId the broadcast it belongs to; `null` for a message sent on its own
     * @param string $status where the message stands; `MessageStatus` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $from the sender's address
     * @param string $subject the subject line
     * @param null|string $tag the label it was sent with
     * @param array<array-key, string> $metadata the pairs it was sent with, their keys as they were sent
     * @param \DateTimeImmutable $submittedAt when it was accepted, in UTC
     * @param bool $test whether it went through a test server, its events simulated
     * @param list<MessageRecipient> $recipients each recipient, and where its delivery stands
     * @param list<MessageAttachment> $attachments each attachment, described
     * @param list<MessageEvent> $events every event, oldest first
     */
    public function __construct(
        public string $messageId,
        public string $streamId,
        public ?string $broadcastId,
        #[Values(MessageStatus::class)]
        public string $status,
        public string $from,
        public string $subject,
        public ?string $tag,
        public array $metadata,
        public \DateTimeImmutable $submittedAt,
        public bool $test,
        public array $recipients,
        public array $attachments,
        public array $events,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            messageId: Json::string($json, 'messageId'),
            streamId: Json::string($json, 'streamId'),
            broadcastId: Json::nullableString($json, 'broadcastId'),
            status: Json::string($json, 'status'),
            from: Json::string($json, 'from'),
            subject: Json::string($json, 'subject'),
            tag: Json::nullableString($json, 'tag'),
            metadata: Json::map($json, 'metadata'),
            submittedAt: Json::time($json, 'submittedAt'),
            test: Json::bool($json, 'test'),
            recipients: \array_map(MessageRecipient::fromWire(...), Json::objects($json, 'recipients')),
            attachments: \array_map(MessageAttachment::fromWire(...), Json::objects($json, 'attachments')),
            events: \array_map(MessageEvent::fromWire(...), Json::objects($json, 'events')),
            json: $json,
        );
    }
}
