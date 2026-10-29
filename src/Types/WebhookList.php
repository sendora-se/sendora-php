<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** Every webhook of the server. */
final readonly class WebhookList implements \JsonSerializable
{
    use KeepsJson;

    /** @param list<Webhook> $webhooks the webhooks */
    public function __construct(
        public array $webhooks,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            webhooks: \array_map(Webhook::fromWire(...), Json::objects($json, 'webhooks')),
            json: $json,
        );
    }
}
