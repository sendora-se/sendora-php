<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** Whether a message's opens and clicks are recorded now, and why tracking may have stayed out. */
final readonly class MessageTracking implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param bool $recordingOpens whether new opens of this message are recorded now, by its own setting, then its broadcast's, then its stream's at this moment; this says nothing of what a copy already sent carries
     * @param bool $recordingClicks the same for clicks on its links
     * @param null|string $skipped why tracking stayed out of at least one copy; each recipient's `tracked` says whether its copy was tracked; `TrackingSkipped` lists the values this release knows, so a `match` over it needs a `default` arm
     */
    public function __construct(
        public bool $recordingOpens,
        public bool $recordingClicks,
        #[Values(TrackingSkipped::class)]
        public ?string $skipped,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            recordingOpens: Json::bool($json, 'recordingOpens'),
            recordingClicks: Json::bool($json, 'recordingClicks'),
            skipped: Json::nullableString($json, 'skipped'),
            json: $json,
        );
    }
}
