<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/**
 * A key just created, with its value, shown this once. `var_dump()`,
 * `print_r()` and `json_encode()` leave the value out, so a logger never
 * writes it. `toArray()` and `var_export()` show it, and so does Symfony's
 * `dump()`, which reads the properties directly.
 */
final readonly class CreatedToken implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $tokenId the key's id
     * @param string $name the name given at creation
     * @param string $prefix the key's first characters, to match it with a value in hand
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param null|\DateTimeImmutable $revokedAt when it was revoked; it then works no more
     * @param string $token the key itself; store it now, since it is never shown again
     */
    public function __construct(
        public string $tokenId,
        public string $name,
        public string $prefix,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $revokedAt,
        #[\SensitiveParameter]
        public string $token,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            tokenId: Json::string($json, 'tokenId'),
            name: Json::string($json, 'name'),
            prefix: Json::string($json, 'prefix'),
            createdAt: Json::time($json, 'createdAt'),
            revokedAt: Json::nullableTime($json, 'revokedAt'),
            token: Json::string($json, 'token'),
            json: $json,
        );
    }

    /** The JSON the key was built from, written back as it came but without its value. */
    public function jsonSerialize(): \stdClass
    {
        $json = $this->json?->copy() ?? new \stdClass();
        unset($json->token);

        return $json;
    }

    /**
     * Leaves out the key's value, and the kept JSON, which holds it too.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'tokenId' => $this->tokenId,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'createdAt' => $this->createdAt,
            'revokedAt' => $this->revokedAt,
        ];
    }
}
