<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;
use Sendora\Types\TrackedLink;
use Sendora\Types\TrackingAgent;
use Sendora\Types\TrackingAutomated;
use Sendora\Types\TrackingClient;
use Sendora\Types\TrackingDevice;
use Sendora\Types\TrackingReason;

/** What tracking knows of a click. */
final readonly class ClickedDetails implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $automated whether a machine rather than a person did it; `TrackingAutomated` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|string $agent the proxy or machine it came through, where Sendora knows it; `TrackingAgent` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param list<string> $reasons why it was labelled a machine's; `TrackingReason` lists the values this release knows, so a `match` over one needs a `default` arm
     * @param null|string $client the mail program, where Sendora knows it; `TrackingClient` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|string $device the kind of device, where Sendora knows it; `TrackingDevice` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param bool $first whether it is the recipient's first event of this kind
     * @param bool $firstHuman whether it is the recipient's first event of this kind that was likely a person's
     * @param TrackedLink $link the link that was followed
     */
    public function __construct(
        #[Values(TrackingAutomated::class)]
        public string $automated,
        #[Values(TrackingAgent::class)]
        public ?string $agent,
        #[Values(TrackingReason::class)]
        public array $reasons,
        #[Values(TrackingClient::class)]
        public ?string $client,
        #[Values(TrackingDevice::class)]
        public ?string $device,
        public bool $first,
        public bool $firstHuman,
        public TrackedLink $link,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            automated: Json::string($json, 'automated'),
            agent: Json::nullableString($json, 'agent'),
            reasons: Json::strings($json, 'reasons'),
            client: Json::nullableString($json, 'client'),
            device: Json::nullableString($json, 'device'),
            first: Json::bool($json, 'first'),
            firstHuman: Json::bool($json, 'firstHuman'),
            link: TrackedLink::fromWire(Json::object($json, 'link')),
            json: $json,
        );
    }
}
