<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A key of the server. Its value is shown only when it is created. */
final readonly class Token implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $tokenId the key's id
     * @param string $name the name given at creation
     * @param string $prefix the key's first characters, to match it with a value in hand
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param null|\DateTimeImmutable $revokedAt when it was revoked; it then works no more
     */
    public function __construct(
        public string $tokenId,
        public string $name,
        public string $prefix,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $revokedAt,
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
            json: $json,
        );
    }
}
