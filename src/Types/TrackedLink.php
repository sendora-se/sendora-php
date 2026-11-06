<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** The link a recipient clicked. */
final readonly class TrackedLink implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param int $index its position in the HTML, the same in every copy, counting from 0
     * @param null|string $url where it led; `null` once it has gone with the message's text and HTML
     */
    public function __construct(
        public int $index,
        public ?string $url,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            index: Json::int($json, 'index'),
            url: Json::nullableString($json, 'url'),
            json: $json,
        );
    }
}
