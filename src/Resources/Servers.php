<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\ConflictException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Wire;
use Sendora\Types\CreatedServer;
use Sendora\Types\Server;
use Sendora\Types\ServerList;

/**
 * Creates, reads, renames and removes the account's servers, each with
 * its own keys, streams, message log and webhooks. `tokens` manages each
 * server's keys.
 *
 * @final
 */
class Servers
{
    /** Creates, reads and revokes the keys of one of the account's servers. */
    public readonly ServerTokens $tokens;

    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {
        $this->tokens = new ServerTokens($caller);
    }

    /**
     * Creates a server with its default transactional stream and its first
     * key, whose value is returned this once. A create is never repeated
     * after a lost connection, which may have created the server.
     *
     * ```php
     * $server = $account->servers->create(name: 'Fakturering');
     * $sendora = new Sendora(token: $server->token->token);
     * ```
     *
     * @param string $name 1 to 100 characters, unique within the account
     * @param null|string $mode `test` for a server whose mail reaches nobody, as `ServerMode` lists them; `live` when left out. It is fixed from creation
     *
     * @throws ConflictException the name is taken (`server_exists`), or the account holds its most servers (`server_limit`, with `max`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(string $name, ?string $mode = null): CreatedServer
    {
        return $this->caller->json(
            new Call('POST', '/v1/servers', idempotent: false, body: Wire::body(['name' => $name, 'mode' => $mode])),
            CreatedServer::fromWire(...),
        );
    }

    /**
     * Every server of the account, oldest first.
     *
     * ```php
     * foreach ($account->servers->list()->servers as $server) {
     *     echo $server->name, ' ', $server->mode, "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): ServerList
    {
        return $this->caller->json(
            new Call('GET', '/v1/servers', idempotent: true),
            ServerList::fromWire(...),
        );
    }

    /**
     * One server by its id.
     *
     * ```php
     * $server = $account->servers->get($serverId);
     * ```
     *
     * @throws NotFoundException the account has no server with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $serverId): Server
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/servers/{}', $serverId), idempotent: true),
            Server::fromWire(...),
        );
    }

    /**
     * Renames the server. Its id, its keys and everything it holds stay.
     *
     * ```php
     * $account->servers->update($serverId, name: 'Fakturor');
     * ```
     *
     * @param string $name the new name, 1 to 100 characters, unique within the account
     *
     * @throws ConflictException another server has the name (`server_exists`), or it is Sendora's own server (`server_reserved`)
     * @throws NotFoundException the account has no server with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent, or the id is empty, `.` or `..`; nothing was sent
     */
    public function update(string $serverId, string $name): Server
    {
        return $this->caller->json(
            new Call('PATCH', Wire::path('/v1/servers/{}', $serverId), idempotent: true, body: Wire::body(['name' => $name])),
            Server::fromWire(...),
        );
    }

    /**
     * Removes the server with its keys, streams, messages and webhooks. It
     * is refused while the server's messages are still being delivered, so
     * try again once they have left. A delete repeated after a lost answer
     * whose first attempt took effect throws `not_found`.
     *
     * ```php
     * $account->servers->delete($serverId);
     * ```
     *
     * @throws ConflictException its messages are still on their way (`server_in_flight`), or it is Sendora's own server (`server_reserved`)
     * @throws NotFoundException the account has no server with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function delete(string $serverId): void
    {
        $this->caller->nothing(new Call('DELETE', Wire::path('/v1/servers/{}', $serverId), idempotent: true, expect: Expect::Nothing));
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
