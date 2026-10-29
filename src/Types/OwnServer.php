<?php

declare(strict_types=1);

namespace Sendora\Types;

use Sendora\Internal\Json;
use Sendora\Internal\JsonObject;
use Sendora\Internal\KeepsJson;
use Sendora\Internal\Values;

/** The server that the key belongs to, its limits and the domains it may send from. */
final readonly class OwnServer implements \JsonSerializable
{
    use KeepsJson;

    /**
     * @param string $serverId the id that every key, stream and message of the server refers to
     * @param string $name the name given at creation, unique within the account
     * @param string $mode whether it delivers; a test server's keys start `sk_test_`. `ServerMode` lists the values this release knows, so a `match` over it needs a `default` arm
     * @param \DateTimeImmutable $createdAt when it was created, in UTC
     * @param list<ServerLimit> $limits on a live server the account's and the server's own, on a test server the test cap, each with its use
     * @param null|InboundCap $inboundCap the account's monthly cap on received mail, and its use; `null` on a test server, which receives no mail. Past the cap, mail to the account is refused for now, so senders retry
     * @param list<SendableDomain> $sendingDomains the account's sending domains that a From address on this server may use now: every verified one, and one in its 24-hour warning with `failingSince` set. An account key adds a sending domain
     */
    public function __construct(
        public string $serverId,
        public string $name,
        #[Values(ServerMode::class)]
        public string $mode,
        public \DateTimeImmutable $createdAt,
        public array $limits,
        public ?InboundCap $inboundCap,
        public array $sendingDomains,
        private ?JsonObject $json = null,
    ) {}

    /** @internal */
    public static function fromWire(#[\SensitiveParameter] JsonObject $json): self
    {
        return new self(
            serverId: Json::string($json, 'serverId'),
            name: Json::string($json, 'name'),
            mode: Json::string($json, 'mode'),
            createdAt: Json::time($json, 'createdAt'),
            limits: \array_map(ServerLimit::fromWire(...), Json::objects($json, 'limits')),
            inboundCap: Json::nullableObject($json, 'inboundCap', InboundCap::fromWire(...)),
            sendingDomains: \array_map(SendableDomain::fromWire(...), Json::objects($json, 'sendingDomains')),
            json: $json,
        );
    }
}
