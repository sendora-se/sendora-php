<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** An attachment as the message log describes it. Its content is never returned. */
final readonly class MessageAttachment implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $name the file name
     * @param string $contentType the media type
     * @param null|string $contentId the id the HTML refers to it by, if any
     * @param int $size the bytes before encoding
     */
    public function __construct(
        public string $name,
        public string $contentType,
        public ?string $contentId,
        public int $size,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            name: Json::string($json, 'name'),
            contentType: Json::string($json, 'contentType'),
            contentId: Json::nullableString($json, 'contentId'),
            size: Json::int($json, 'size'),
            json: $json,
        );
    }
}
