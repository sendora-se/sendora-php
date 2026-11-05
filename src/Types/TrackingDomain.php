<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A tracking domain, the record it needs and what the last check found. */
final readonly class TrackingDomain implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $trackingDomainId the tracking domain's id
     * @param string $host the host, lower-cased and IDNA-encoded
     * @param null|string $sendingDomainId the sending domain whose new mail will use the host; null once that domain is deleted
     * @param string $state where it stands; `TrackingDomainState` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $createdAt when it was added, in UTC
     * @param null|\DateTimeImmutable $lastCheckedAt when the records were last looked up
     * @param null|\DateTimeImmutable $certificateReadyAt when the certificate was proved to work
     * @param null|\DateTimeImmutable $failingSince when a check first found the records wrong on a host whose records have been right
     * @param null|\DateTimeImmutable $expiresAt when a host whose records have never been right goes
     * @param TrackingCnameRecord $cname the CNAME record to add
     * @param TrackingCaaResult $caa whether CAA records let Let's Encrypt issue the certificate
     */
    public function __construct(
        public string $trackingDomainId,
        public string $host,
        public ?string $sendingDomainId,
        #[Values(TrackingDomainState::class)]
        public string $state,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastCheckedAt,
        public ?\DateTimeImmutable $certificateReadyAt,
        public ?\DateTimeImmutable $failingSince,
        public ?\DateTimeImmutable $expiresAt,
        public TrackingCnameRecord $cname,
        public TrackingCaaResult $caa,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            trackingDomainId: Json::string($json, 'trackingDomainId'),
            host: Json::string($json, 'host'),
            sendingDomainId: Json::nullableString($json, 'sendingDomainId'),
            state: Json::string($json, 'state'),
            createdAt: Json::time($json, 'createdAt'),
            lastCheckedAt: Json::nullableTime($json, 'lastCheckedAt'),
            certificateReadyAt: Json::nullableTime($json, 'certificateReadyAt'),
            failingSince: Json::nullableTime($json, 'failingSince'),
            expiresAt: Json::nullableTime($json, 'expiresAt'),
            cname: TrackingCnameRecord::fromWire(Json::object($json, 'cname')),
            caa: TrackingCaaResult::fromWire(Json::object($json, 'caa')),
            json: $json,
        );
    }
}
