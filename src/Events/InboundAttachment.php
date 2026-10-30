<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** An attachment of a received message as an `inbound` event describes it. `inbound->attachment()` downloads its bytes. */
final readonly class InboundAttachment implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param int $position its place among the message's attachments, from 0
     * @param string $name the file name
     * @param string $contentType the type that the sender declared
     * @param null|string $contentId the Content-ID that an HTML body refers to with `cid:`
     * @param int $size its size in bytes
     * @param bool $inline whether it is shown in the body rather than offered as a file
     */
    public function __construct(
        public int $position,
        public string $name,
        public string $contentType,
        public ?string $contentId,
        public int $size,
        public bool $inline,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            position: Json::int($json, 'position'),
            name: Json::string($json, 'name'),
            contentType: Json::string($json, 'contentType'),
            contentId: Json::nullableString($json, 'contentId'),
            size: Json::int($json, 'size'),
            inline: Json::bool($json, 'inline'),
            json: $json,
        );
    }
}
