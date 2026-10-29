<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Types\OwnServer;

/**
 * Reads the key's own server: its mode, its limits and the domains it may
 * send from.
 *
 * @final
 */
class ThisServer
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * The key's server: whether it is live or a test server, every limit on
     * what it sends with how much of it this minute and this month have
     * used, and the account's domains that a From address may use.
     *
     * ```php
     * $server = $sendora->server->get();
     * if ($production && $server->mode === ServerMode::Test->value) {
     *     throw new RuntimeException("This key's server delivers nothing.");
     * }
     * if ($server->sendingDomains === []) {
     *     throw new RuntimeException('No sending domain is verified yet.');
     * }
     * $sender = "no-reply@{$server->sendingDomains[0]->domain}";
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function get(): OwnServer
    {
        return $this->caller->json(
            new Call('GET', '/v1/server', idempotent: true),
            OwnServer::fromWire(...),
        );
    }

    /**
     * Shows the API's address alone.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->caller->baseUrl];
    }
}
