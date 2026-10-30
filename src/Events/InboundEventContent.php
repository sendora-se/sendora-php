<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;
use Sendora\Types\InboundAddress;
use Sendora\Types\InboundEnvelope;
use Sendora\Types\InboundHeader;

/**
 * The parsed message that an `inbound` event carries when its webhook's
 * `inboundContent` is `full`. The wire sends these fields beside the
 * event's own; the SDK gathers them here.
 */
final readonly class InboundEventContent implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param null|InboundEnvelope $envelope the envelope that it came in
     * @param null|InboundAddress $from the From header
     * @param list<InboundAddress> $replyTo the Reply-To addresses
     * @param list<InboundAddress> $to up to 100 of the To addresses; `toCount` says how many there are
     * @param int $toCount how many To addresses it had
     * @param list<InboundAddress> $cc up to 100 of the Cc addresses; `ccCount` says how many there are
     * @param int $ccCount how many Cc addresses it had
     * @param null|string $subject the subject
     * @param null|\DateTimeImmutable $date the sender's Date header, when it named a real moment
     * @param null|string $messageIdHeader its Message-ID header
     * @param null|string $inReplyTo its In-Reply-To header
     * @param list<string> $references its References header, one id each
     * @param list<InboundHeader> $headers every header in order, each name as received
     * @param null|string $text the plain-text body
     * @param null|string $html the HTML body as received
     * @param list<InboundAttachment> $attachments each attachment, described
     * @param null|string $contentOmitted why the content was left out, `too_large` or `expired`; `null` when it is all here. `ContentOmitted` lists the values this release knows, so a `match` over it needs a `default` arm
     */
    public function __construct(
        public ?InboundEnvelope $envelope,
        public ?InboundAddress $from,
        public array $replyTo,
        public array $to,
        public int $toCount,
        public array $cc,
        public int $ccCount,
        public ?string $subject,
        public ?\DateTimeImmutable $date,
        public ?string $messageIdHeader,
        public ?string $inReplyTo,
        public array $references,
        public array $headers,
        public ?string $text,
        public ?string $html,
        public array $attachments,
        #[Values(ContentOmitted::class)]
        public ?string $contentOmitted,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            envelope: Json::nullableObject($json, 'envelope', InboundEnvelope::fromWire(...)),
            from: Json::nullableObject($json, 'from', InboundAddress::fromWire(...)),
            replyTo: \array_map(InboundAddress::fromWire(...), Json::objects($json, 'replyTo')),
            to: \array_map(InboundAddress::fromWire(...), Json::objects($json, 'to')),
            toCount: Json::int($json, 'toCount'),
            cc: \array_map(InboundAddress::fromWire(...), Json::objects($json, 'cc')),
            ccCount: Json::int($json, 'ccCount'),
            subject: Json::nullableString($json, 'subject'),
            date: Json::nullableTime($json, 'date'),
            messageIdHeader: Json::nullableString($json, 'messageIdHeader'),
            inReplyTo: Json::nullableString($json, 'inReplyTo'),
            references: Json::strings($json, 'references'),
            headers: \array_map(InboundHeader::fromWire(...), Json::objects($json, 'headers')),
            text: Json::nullableString($json, 'text'),
            html: Json::nullableString($json, 'html'),
            attachments: \array_map(InboundAttachment::fromWire(...), Json::objects($json, 'attachments')),
            contentOmitted: Json::nullableString($json, 'contentOmitted'),
            json: $json,
        );
    }
}
