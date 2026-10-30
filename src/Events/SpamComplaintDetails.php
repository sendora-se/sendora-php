<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** What the receiver reported with a spam complaint. */
final readonly class SpamComplaintDetails implements \JsonSerializable
{
    use KeepsJson;

    /** @param null|string $feedbackType the feedback type it reported, such as `abuse` */
    public function __construct(
        public ?string $feedbackType,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            feedbackType: Json::nullableString($json, 'feedbackType'),
            json: $json,
        );
    }
}
