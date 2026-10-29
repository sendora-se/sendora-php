<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;

/**
 * A message accepted for delivery. Delivery, deferral, bounce and
 * complaint come later, as events on the message and as webhooks.
 */
final readonly class AcceptedEmail implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $messageId the id that the message log, its events and the webhooks refer to
     * @param string $status `accepted`
     * @param \DateTimeImmutable $submittedAt when the message was accepted, in UTC
     * @param bool $test whether the server is a test server, whose mail goes through everything but delivery and reaches nobody
     */
    public function __construct(
        public string $messageId,
        public string $status,
        public \DateTimeImmutable $submittedAt,
        public bool $test,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            messageId: Json::string($json, 'messageId'),
            status: Json::string($json, 'status'),
            submittedAt: Json::time($json, 'submittedAt'),
            test: Json::bool($json, 'test'),
            json: $json,
        );
    }
}
