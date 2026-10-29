<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** What each lookup of an inbound domain's check found. */
final readonly class InboundDomainCheck implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $mx the MX record's lookup; `RecordCheck` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $txt the TXT record's lookup, from the same set
     */
    public function __construct(
        #[Values(RecordCheck::class)]
        public string $mx,
        #[Values(RecordCheck::class)]
        public string $txt,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            mx: Json::string($json, 'mx'),
            txt: Json::string($json, 'txt'),
            json: $json,
        );
    }
}
