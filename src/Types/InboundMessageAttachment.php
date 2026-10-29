<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** An attachment of a received message, described. `inbound->attachment()` downloads its bytes. */
final readonly class InboundMessageAttachment implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $attachmentId the attachment's id
     * @param int $position its place among the message's attachments, from 0
     * @param null|string $name the file name, made safe; `null` once the content is gone
     * @param null|string $contentType the type that the sender declared; every download is served as `application/octet-stream`
     * @param null|string $contentId the Content-ID that an HTML body refers to with `cid:`
     * @param int $size its size in bytes
     * @param bool $inline whether it is shown in the body rather than offered as a file
     */
    public function __construct(
        public string $attachmentId,
        public int $position,
        public ?string $name,
        public ?string $contentType,
        public ?string $contentId,
        public int $size,
        public bool $inline,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            attachmentId: Json::string($json, 'attachmentId'),
            position: Json::int($json, 'position'),
            name: Json::nullableString($json, 'name'),
            contentType: Json::nullableString($json, 'contentType'),
            contentId: Json::nullableString($json, 'contentId'),
            size: Json::int($json, 'size'),
            inline: Json::bool($json, 'inline'),
            json: $json,
        );
    }
}
