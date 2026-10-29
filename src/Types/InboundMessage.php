<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A received message as a search shows it. */
final readonly class InboundMessage implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $inboundMessageId the message's id
     * @param string $streamId the inbound stream that received it
     * @param \DateTimeImmutable $receivedAt when it was accepted from the sending server, in UTC
     * @param string $envelopeRecipient the address of yours that it was sent to
     * @param null|string $mailboxHash the text after the plus sign in that address, when the sender used one
     * @param int $sizeBytes its size in bytes
     * @param int $attachmentCount how many attachments it has
     * @param bool $hasText whether it has a plain-text body
     * @param bool $hasHtml whether it has an HTML body
     * @param null|string $parseIssue what the parser met, from a closed set; `null` when it parsed clean
     * @param InboundAuthentication $authentication what its authentication checks found
     * @param bool $contentAvailable `false` once the stream's content window has passed; only the reference remains then
     * @param \DateTimeImmutable $contentExpiresAt when the content goes, in UTC
     * @param null|InboundAddress $from the From header; `null` without one, or once the content is gone
     * @param null|string $subject the subject, while the content is there
     * @param null|\DateTimeImmutable $date the sender's Date header, when it named a real moment
     */
    public function __construct(
        public string $inboundMessageId,
        public string $streamId,
        public \DateTimeImmutable $receivedAt,
        public string $envelopeRecipient,
        public ?string $mailboxHash,
        public int $sizeBytes,
        public int $attachmentCount,
        public bool $hasText,
        public bool $hasHtml,
        public ?string $parseIssue,
        public InboundAuthentication $authentication,
        public bool $contentAvailable,
        public \DateTimeImmutable $contentExpiresAt,
        public ?InboundAddress $from,
        public ?string $subject,
        public ?\DateTimeImmutable $date,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            inboundMessageId: Json::string($json, 'inboundMessageId'),
            streamId: Json::string($json, 'streamId'),
            receivedAt: Json::time($json, 'receivedAt'),
            envelopeRecipient: Json::string($json, 'envelopeRecipient'),
            mailboxHash: Json::nullableString($json, 'mailboxHash'),
            sizeBytes: Json::int($json, 'sizeBytes'),
            attachmentCount: Json::int($json, 'attachmentCount'),
            hasText: Json::bool($json, 'hasText'),
            hasHtml: Json::bool($json, 'hasHtml'),
            parseIssue: Json::nullableString($json, 'parseIssue'),
            authentication: InboundAuthentication::fromWire(Json::object($json, 'authentication')),
            contentAvailable: Json::bool($json, 'contentAvailable'),
            contentExpiresAt: Json::time($json, 'contentExpiresAt'),
            from: Json::nullableObject($json, 'from', InboundAddress::fromWire(...)),
            subject: Json::nullableString($json, 'subject'),
            date: Json::nullableTime($json, 'date'),
            json: $json,
        );
    }
}
