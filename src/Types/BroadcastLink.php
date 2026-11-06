<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A tracked link of a broadcast that has a click. */
final readonly class BroadcastLink implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param int $index its position in the HTML, the same in every copy, counting from 0
     * @param null|string $url where the first click led, since a substitution can give a link another URL per recipient; `null` once it has gone with the content
     * @param int $clicked the recipients who clicked this link, machines' included
     * @param int $clickedByPeople those of them likely to be people
     */
    public function __construct(
        public int $index,
        public ?string $url,
        public int $clicked,
        public int $clickedByPeople,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            index: Json::int($json, 'index'),
            url: Json::nullableString($json, 'url'),
            clicked: Json::int($json, 'clicked'),
            clickedByPeople: Json::int($json, 'clickedByPeople'),
            json: $json,
        );
    }
}
