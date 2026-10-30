<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** What each lookup of a sending domain's check found. */
final readonly class DomainCheck implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $returnPath the return-path record's lookup; `RecordCheck` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $dkim the DKIM record's lookup, from the same set
     */
    public function __construct(
        #[Values(RecordCheck::class)]
        public string $returnPath,
        #[Values(RecordCheck::class)]
        public string $dkim,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            returnPath: Json::string($json, 'returnPath'),
            dkim: Json::string($json, 'dkim'),
            json: $json,
        );
    }
}
