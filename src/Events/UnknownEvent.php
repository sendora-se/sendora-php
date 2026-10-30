<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;

/**
 * A verified event that this release cannot read, its payload as it came:
 * an event newer than the release, or a known one whose body the release
 * cannot read. The signature has proved that it came from Sendora, so it
 * is handed over rather than refused.
 */
final readonly class UnknownEvent extends WebhookEvent
{
    /**
     * @param string $id the delivery's id; a delivery arrives at least once, so key your handling on it
     * @param int $attempt the attempt number, from 1
     * @param string $event the event's name, as sent
     * @param array<array-key, mixed> $data the whole payload as it came, as `json_decode()` with `true` reads it
     */
    public function __construct(
        string $id,
        int $attempt,
        string $event,
        public array $data,
        ?JsonObject $json = null,
    ) {
        parent::__construct($id, $attempt, $event, $json);
    }

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            id: Json::string($json, 'id'),
            attempt: Json::int($json, 'attempt'),
            event: Json::string($json, 'event'),
            data: $json->toArray(),
            json: $json,
        );
    }
}
