<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A server of the account: its own keys, streams, message log, suppressions and webhooks. */
final readonly class Server implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $serverId the id that every key, stream and message of the server refers to
     * @param string $name the name given at creation, unique within the account
     * @param string $mode whether it delivers; a test server's keys start `sk_test_`. `ServerMode` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     */
    public function __construct(
        public string $serverId,
        public string $name,
        #[Values(ServerMode::class)]
        public string $mode,
        public \DateTimeImmutable $createdAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            serverId: Json::string($json, 'serverId'),
            name: Json::string($json, 'name'),
            mode: Json::string($json, 'mode'),
            createdAt: Json::time($json, 'createdAt'),
            json: $json,
        );
    }
}
