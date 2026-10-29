<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A domain on an inbound stream, and the two records it needs. */
final readonly class InboundDomain implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $inboundDomainId the domain's id
     * @param string $streamId the inbound stream it delivers to
     * @param string $domain the domain, in lower case and IDNA-encoded
     * @param bool $verified whether both records are seen; only then is mail to it accepted
     * @param \DateTimeImmutable $createdAt when it was claimed, in UTC
     * @param null|\DateTimeImmutable $lastCheckedAt when the records were last looked up
     * @param null|\DateTimeImmutable $unverifiedAt when a verified domain lost its records; mail to it is deferred for 72 hours from then and refused after, until the records are back
     * @param MxRecord $mx the MX record that brings its mail to Sendora
     * @param TxtRecord $txt the TXT record that proves the claim, holding a token that only you were shown
     */
    public function __construct(
        public string $inboundDomainId,
        public string $streamId,
        public string $domain,
        public bool $verified,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastCheckedAt,
        public ?\DateTimeImmutable $unverifiedAt,
        public MxRecord $mx,
        public TxtRecord $txt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            inboundDomainId: Json::string($json, 'inboundDomainId'),
            streamId: Json::string($json, 'streamId'),
            domain: Json::string($json, 'domain'),
            verified: Json::bool($json, 'verified'),
            createdAt: Json::time($json, 'createdAt'),
            lastCheckedAt: Json::nullableTime($json, 'lastCheckedAt'),
            unverifiedAt: Json::nullableTime($json, 'unverifiedAt'),
            mx: MxRecord::fromWire(Json::object($json, 'mx')),
            txt: TxtRecord::fromWire(Json::object($json, 'txt')),
            json: $json,
        );
    }
}
