<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Types\InboundAuthentication;

/** A message was received on an inbound stream. */
final readonly class InboundEvent extends WebhookEvent
{
    /**
     * @param string $id the delivery's id; a delivery arrives at least once, so key your handling on it
     * @param int $attempt the attempt number, from 1
     * @param string $inboundMessageId the received message, for `inbound->get()`
     * @param string $serverId the server that received it
     * @param string $streamId the inbound stream that received it
     * @param \DateTimeImmutable $receivedAt when it was accepted from the sending server, in UTC
     * @param string $envelopeRecipient the address of yours that it was sent to
     * @param null|string $mailboxHash the text after the plus sign in that address, when the sender used one
     * @param int $sizeBytes its size in bytes
     * @param int $attachmentCount how many attachments it has
     * @param bool $hasText whether it has a plain-text body
     * @param bool $hasHtml whether it has an HTML body
     * @param InboundAuthentication $authentication what its authentication checks found
     * @param null|string $parseIssue what the parser met, from a closed set; `null` when it parsed clean
     * @param null|InboundEventContent $content the parsed message, for a webhook whose `inboundContent` is `full`; `null` for `reference`
     */
    public function __construct(
        string $id,
        int $attempt,
        public string $inboundMessageId,
        public string $serverId,
        public string $streamId,
        public \DateTimeImmutable $receivedAt,
        public string $envelopeRecipient,
        public ?string $mailboxHash,
        public int $sizeBytes,
        public int $attachmentCount,
        public bool $hasText,
        public bool $hasHtml,
        public InboundAuthentication $authentication,
        public ?string $parseIssue,
        public ?InboundEventContent $content,
        ?JsonObject $json = null,
    ) {
        parent::__construct($id, $attempt, 'inbound', $json);
    }

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            id: Json::string($json, 'id'),
            attempt: Json::int($json, 'attempt'),
            inboundMessageId: Json::string($json, 'inboundMessageId'),
            serverId: Json::string($json, 'serverId'),
            streamId: Json::string($json, 'streamId'),
            receivedAt: Json::time($json, 'receivedAt'),
            envelopeRecipient: Json::string($json, 'envelopeRecipient'),
            mailboxHash: Json::nullableString($json, 'mailboxHash'),
            sizeBytes: Json::int($json, 'sizeBytes'),
            attachmentCount: Json::int($json, 'attachmentCount'),
            hasText: Json::bool($json, 'hasText'),
            hasHtml: Json::bool($json, 'hasHtml'),
            authentication: InboundAuthentication::fromWire(Json::object($json, 'authentication')),
            parseIssue: Json::nullableString($json, 'parseIssue'),
            // The wire merges a full event's content into the event and marks it with contentOmitted.
            content: \property_exists($json->object, 'contentOmitted') ? InboundEventContent::fromWire($json) : null,
            json: $json,
        );
    }
}
