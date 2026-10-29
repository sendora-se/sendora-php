<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** One page of the message log, newest first. */
final readonly class MessagePage implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param list<Message> $messages the page's messages
     * @param null|string $next pass as `after` for the next page; `null` on the last
     */
    public function __construct(
        public array $messages,
        public ?string $next,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            messages: \array_map(Message::fromWire(...), Json::objects($json, 'messages')),
            next: Json::nullableString($json, 'next'),
            json: $json,
        );
    }
}
