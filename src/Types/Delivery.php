<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** One event on its way to a webhook, retried until it is delivered or dead. */
final readonly class Delivery implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $deliveryId the delivery's id
     * @param string $event the event it carries; `WebhookEventName` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|string $messageId the message it is about; `null` for an event about usage
     * @param null|string $inboundMessageId the received message that an `inbound` event is about; `null` for every other event
     * @param string $status where it stands; `DeliveryStatus` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param int $attempts how many times it was posted
     * @param null|\DateTimeImmutable $nextAttemptAt when the next attempt is due, while it is pending
     * @param null|int $lastStatusCode the HTTP status that the endpoint last answered
     * @param null|string $lastError why the last attempt failed, when it did
     * @param null|\DateTimeImmutable $deliveredAt when the endpoint took it
     * @param \DateTimeImmutable $createdAt when it was queued, in UTC
     */
    public function __construct(
        public string $deliveryId,
        #[Values(WebhookEventName::class)]
        public string $event,
        public ?string $messageId,
        public ?string $inboundMessageId,
        #[Values(DeliveryStatus::class)]
        public string $status,
        public int $attempts,
        public ?\DateTimeImmutable $nextAttemptAt,
        public ?int $lastStatusCode,
        public ?string $lastError,
        public ?\DateTimeImmutable $deliveredAt,
        public \DateTimeImmutable $createdAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            deliveryId: Json::string($json, 'deliveryId'),
            event: Json::string($json, 'event'),
            messageId: Json::nullableString($json, 'messageId'),
            inboundMessageId: Json::nullableString($json, 'inboundMessageId'),
            status: Json::string($json, 'status'),
            attempts: Json::int($json, 'attempts'),
            nextAttemptAt: Json::nullableTime($json, 'nextAttemptAt'),
            lastStatusCode: Json::nullableInt($json, 'lastStatusCode'),
            lastError: Json::nullableString($json, 'lastError'),
            deliveredAt: Json::nullableTime($json, 'deliveredAt'),
            createdAt: Json::time($json, 'createdAt'),
            json: $json,
        );
    }
}
