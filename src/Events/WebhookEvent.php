<?php

declare(strict_types=1);

namespace Sendora\Events;

use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/**
 * An event that a webhook delivery carries, as `WebhookVerifier::verify()`
 * returns it. Tell the kinds apart with `instanceof`; an event this release
 * cannot read is an `UnknownEvent`. A delivery arrives at least once, so key
 * your handling on `id`.
 */
abstract readonly class WebhookEvent implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $id the delivery's id; a delivery arrives at least once, so key your handling on it
     * @param int $attempt the attempt number, from 1
     * @param string $event the event's name, as sent
     */
    public function __construct(
        public string $id,
        public int $attempt,
        public string $event,
        private ?JsonObject $json = null,
    ) {}
}
