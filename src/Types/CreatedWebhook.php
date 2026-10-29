<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/**
 * A webhook just created, with its signing secret, shown this once.
 * `var_dump()`, `print_r()` and `json_encode()` leave the secret out, so
 * a logger never writes it. `toArray()` and `var_export()` show it, and so
 * does Symfony's `dump()`, which reads the properties directly.
 */
final readonly class CreatedWebhook implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $webhookId the webhook's id
     * @param string $url where its events are posted
     * @param list<string> $events the events it receives; `WebhookEventName` lists the values this release knows, so a `match` over one needs a `default` arm
     * @param null|string $streamId the one stream whose message events it receives; `null` for every stream
     * @param bool $enabled `false` while it is switched off in the dashboard; nothing is queued for it then
     * @param string $inboundContent how the `inbound` event carries a received message; `InboundContent` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param list<WebhookSecret> $secrets the live secrets: the one in `secret`
     * @param string $secret the signing secret; store it beside the key, since it is never shown again
     */
    public function __construct(
        public string $webhookId,
        public string $url,
        #[Values(WebhookEventName::class)]
        public array $events,
        public ?string $streamId,
        public bool $enabled,
        #[Values(InboundContent::class)]
        public string $inboundContent,
        public \DateTimeImmutable $createdAt,
        public array $secrets,
        #[\SensitiveParameter]
        public string $secret,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            webhookId: Json::string($json, 'webhookId'),
            url: Json::string($json, 'url'),
            events: Json::strings($json, 'events'),
            streamId: Json::nullableString($json, 'streamId'),
            enabled: Json::bool($json, 'enabled'),
            inboundContent: Json::string($json, 'inboundContent'),
            createdAt: Json::time($json, 'createdAt'),
            secrets: \array_map(WebhookSecret::fromWire(...), Json::objects($json, 'secrets')),
            secret: Json::string($json, 'secret'),
            json: $json,
        );
    }

    /** The JSON the webhook was built from, written back as it came but without its secret. */
    public function jsonSerialize(): \stdClass
    {
        $json = $this->json?->copy() ?? new \stdClass();
        unset($json->secret);

        return $json;
    }

    /**
     * Leaves out the secret, and the kept JSON, which holds it too.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'webhookId' => $this->webhookId,
            'url' => $this->url,
            'events' => $this->events,
            'streamId' => $this->streamId,
            'enabled' => $this->enabled,
            'inboundContent' => $this->inboundContent,
            'createdAt' => $this->createdAt,
            'secrets' => $this->secrets,
        ];
    }
}
