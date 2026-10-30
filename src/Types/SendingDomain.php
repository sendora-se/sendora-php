<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** A sending domain of the account, and the two records it needs. */
final readonly class SendingDomain implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $domainId the domain's id
     * @param string $domain the domain that mail is sent from
     * @param bool $verified whether both records count as found; only then may mail go out. A lookup that fails at the resolver never changes it, and a record found missing starts a 24-hour warning (`failingSince`) through which it stays verified
     * @param \DateTimeImmutable $createdAt when it was added, in UTC
     * @param null|\DateTimeImmutable $lastCheckedAt when the records were last looked up
     * @param null|\DateTimeImmutable $failingSince when a check first found a record of this verified domain missing or wrong; the first check 24 hours after that which still misses one ends the verification
     * @param CnameRecord $returnPath the return-path record, which also satisfies SPF
     * @param TxtRecord $dkim the DKIM public key record
     */
    public function __construct(
        public string $domainId,
        public string $domain,
        public bool $verified,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastCheckedAt,
        public ?\DateTimeImmutable $failingSince,
        public CnameRecord $returnPath,
        public TxtRecord $dkim,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            domainId: Json::string($json, 'domainId'),
            domain: Json::string($json, 'domain'),
            verified: Json::bool($json, 'verified'),
            createdAt: Json::time($json, 'createdAt'),
            lastCheckedAt: Json::nullableTime($json, 'lastCheckedAt'),
            failingSince: Json::nullableTime($json, 'failingSince'),
            returnPath: CnameRecord::fromWire(Json::object($json, 'returnPath')),
            dkim: TxtRecord::fromWire(Json::object($json, 'dkim')),
            json: $json,
        );
    }
}
