<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** Whether CAA records let Let's Encrypt issue a tracking domain's certificate. */
final readonly class TrackingCaaResult implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param null|string $check what the last check found, null before the first; `TrackingCaaCheck` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param null|TrackingCaaRecord $record what lets the certificate be issued; null unless the check found a problem
     */
    public function __construct(
        #[Values(TrackingCaaCheck::class)]
        public ?string $check,
        public ?TrackingCaaRecord $record,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            check: Json::nullableString($json, 'check'),
            record: Json::nullableObject($json, 'record', TrackingCaaRecord::fromWire(...)),
            json: $json,
        );
    }
}
