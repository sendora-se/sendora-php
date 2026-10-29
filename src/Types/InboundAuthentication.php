<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** What Sendora found when it checked a received message's authentication. */
final readonly class InboundAuthentication implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $spf SPF for the address in MAIL FROM; `SpfVerdict` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $spfHelo SPF for the name that the sending server gave in HELO, from the same set
     * @param string $dkim the DKIM verdict; `AuthVerdict` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $dmarc the DMARC verdict, from the same set
     * @param null|string $dmarcPolicy what the sender's domain asks for, told only when DMARC failed; `DmarcPolicy` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $arc the ARC verdict; `ArcVerdict` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|\DateTimeImmutable $checkedAt when the checks ran; `null` until they have
     */
    public function __construct(
        #[Values(SpfVerdict::class)]
        public string $spf,
        #[Values(SpfVerdict::class)]
        public string $spfHelo,
        #[Values(AuthVerdict::class)]
        public string $dkim,
        #[Values(AuthVerdict::class)]
        public string $dmarc,
        #[Values(DmarcPolicy::class)]
        public ?string $dmarcPolicy,
        #[Values(ArcVerdict::class)]
        public string $arc,
        public ?\DateTimeImmutable $checkedAt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            spf: Json::string($json, 'spf'),
            spfHelo: Json::string($json, 'spfHelo'),
            dkim: Json::string($json, 'dkim'),
            dmarc: Json::string($json, 'dmarc'),
            dmarcPolicy: Json::nullableString($json, 'dmarcPolicy'),
            arc: Json::string($json, 'arc'),
            checkedAt: Json::nullableTime($json, 'checkedAt'),
            json: $json,
        );
    }
}
