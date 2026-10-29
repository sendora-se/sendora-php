<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** A stream of the server: what a send names as `streamId`. */
final readonly class Stream implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $streamId the stream's id
     * @param string $kind what it carries; `StreamKind` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param string $name the name given at creation, unique within the server
     * @param bool $isDefault whether a send that names no stream goes on it; one per server, transactional
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param null|\DateTimeImmutable $archivedAt when it was archived; it then takes no new messages
     * @param null|\DateTimeImmutable $pausedAt when Sendora paused it after complaints; it takes no messages until support resumes it
     * @param null|string $inboundAddress an inbound stream's address; `null` on every other kind
     * @param null|int $contentRetentionDays how many days an inbound stream keeps received content, 1 to 30; `null` for the default of 30, and on every other kind
     */
    public function __construct(
        public string $streamId,
        #[Values(StreamKind::class)]
        public string $kind,
        public string $name,
        public bool $isDefault,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $archivedAt,
        public ?\DateTimeImmutable $pausedAt,
        public ?string $inboundAddress,
        public ?int $contentRetentionDays,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            streamId: Json::string($json, 'streamId'),
            kind: Json::string($json, 'kind'),
            name: Json::string($json, 'name'),
            isDefault: Json::bool($json, 'isDefault'),
            createdAt: Json::time($json, 'createdAt'),
            archivedAt: Json::nullableTime($json, 'archivedAt'),
            pausedAt: Json::nullableTime($json, 'pausedAt'),
            inboundAddress: Json::nullableString($json, 'inboundAddress'),
            contentRetentionDays: Json::nullableInt($json, 'contentRetentionDays'),
            json: $json,
        );
    }
}
