<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/**
 * A signing secret just created, with its value, shown this once.
 * `var_dump()`, `print_r()` and `json_encode()` leave the value out, so a
 * logger never writes it. `toArray()` and `var_export()` show it, and so
 * does Symfony's `dump()`, which reads the properties directly.
 */
final readonly class CreatedWebhookSecret implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $secretId the secret's id
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param string $secret the secret itself; store it now, since it is never shown again
     */
    public function __construct(
        public string $secretId,
        public \DateTimeImmutable $createdAt,
        #[\SensitiveParameter]
        public string $secret,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            secretId: Json::string($json, 'secretId'),
            createdAt: Json::time($json, 'createdAt'),
            secret: Json::string($json, 'secret'),
            json: $json,
        );
    }

    /** The JSON the secret was built from, written back as it came but without its value. */
    public function jsonSerialize(): \stdClass
    {
        $json = $this->json?->copy() ?? new \stdClass();
        unset($json->secret);

        return $json;
    }

    /**
     * Leaves out the secret's value, and the kept JSON, which holds it too.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'secretId' => $this->secretId,
            'createdAt' => $this->createdAt,
        ];
    }
}
