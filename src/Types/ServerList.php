<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** Every server of the account, oldest first. */
final readonly class ServerList implements \JsonSerializable
{
    use KeepsJson;

    /** @param list<Server> $servers the servers */
    public function __construct(
        public array $servers,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            servers: \array_map(Server::fromWire(...), Json::objects($json, 'servers')),
            json: $json,
        );
    }
}
