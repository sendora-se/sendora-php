<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A live signing secret of a webhook. Its value was shown once, when it was created. */
final readonly class WebhookSecret implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $secretId the secret's id
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     */
    public function __construct(
        public string $secretId,
        public \DateTimeImmutable $createdAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            secretId: Json::string($json, 'secretId'),
            createdAt: Json::time($json, 'createdAt'),
            json: $json,
        );
    }
}
