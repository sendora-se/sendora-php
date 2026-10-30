<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/** What the receiver answered when it asked Sendora to try later. */
final readonly class DeferredDetails implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param null|int $code the SMTP status it answered
     * @param null|string $response its response text
     */
    public function __construct(
        public ?int $code,
        public ?string $response,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            code: Json::nullableInt($json, 'code'),
            response: Json::nullableString($json, 'response'),
            json: $json,
        );
    }
}
