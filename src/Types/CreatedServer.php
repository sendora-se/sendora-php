<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/**
 * A server just created, with its first key, whose value is shown this
 * once. `var_dump()`, `print_r()` and `json_encode()` leave the value out,
 * so a logger never writes it. `toArray()` and `var_export()` show it, and
 * so does Symfony's `dump()`, which reads the properties directly.
 */
final readonly class CreatedServer implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $serverId the id that every key, stream and message of the server refers to
     * @param string $name the name given at creation, unique within the account
     * @param string $mode whether it delivers; a test server's keys start `sk_test_`. `ServerMode` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param CreatedToken $token the server's first key, named `default`; its value in `$token->token` is never shown again
     */
    public function __construct(
        public string $serverId,
        public string $name,
        #[Values(ServerMode::class)]
        public string $mode,
        public \DateTimeImmutable $createdAt,
        #[\SensitiveParameter]
        public CreatedToken $token,
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
            token: CreatedToken::fromWire(Json::object($json, 'token')),
            json: $json,
        );
    }

    /** The JSON the server was built from, written back as it came but without its first key's value. */
    public function jsonSerialize(): \stdClass
    {
        $json = $this->json?->copy();
        if ($json === null) {
            return new \stdClass();
        }
        $json->token = $this->token->jsonSerialize();

        return $json;
    }

    /**
     * Leaves out the kept JSON, which holds the first key's value. The key
     * itself hides its value.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'serverId' => $this->serverId,
            'name' => $this->name,
            'mode' => $this->mode,
            'createdAt' => $this->createdAt,
            'token' => $this->token,
        ];
    }
}
