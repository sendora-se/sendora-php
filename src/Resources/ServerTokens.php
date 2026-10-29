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
use Sendora\Types\CreatedToken;
use Sendora\Types\Token;
use Sendora\Types\TokenList;

/**
 * Creates, reads and revokes the keys of one of the account's servers. A
 * server holds at most two live keys.
 *
 * @final
 */
class ServerTokens
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Creates another key for the server and returns its value, shown this
     * once. A rotation is create, switch, revoke. A create is never
     * repeated after a lost connection, which may have created the key.
     *
     * ```php
     * $created = $account->servers->tokens->create($serverId, name: 'CI');
     * $vault->store('sendora', $created->token);
     * ```
     *
     * @param string $name 1 to 100 characters, for people to tell keys apart
     *
     * @throws ConflictException the server holds two live keys (`token_limit`)
     * @throws NotFoundException the account has no server with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent, or the id is empty, `.` or `..`; nothing was sent
     */
    public function create(string $serverId, string $name): CreatedToken
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/servers/{}/tokens', $serverId), idempotent: false, body: Wire::body(['name' => $name])),
            CreatedToken::fromWire(...),
        );
    }

    /**
     * Every key of the server, revoked ones included, without their values.
     *
     * ```php
     * foreach ($account->servers->tokens->list($serverId)->tokens as $key) {
     *     echo $key->name, ' ', $key->prefix, "\n";
     * }
     * ```
     *
     * @throws NotFoundException the account has no server with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function list(string $serverId): TokenList
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/servers/{}/tokens', $serverId), idempotent: true),
            TokenList::fromWire(...),
        );
    }

    /**
     * One key by its id. Its value is never part of the answer.
     *
     * ```php
     * $key = $account->servers->tokens->get($serverId, $tokenId);
     * ```
     *
     * @throws NotFoundException the account has no such server or key
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $serverId, string $tokenId): Token
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/servers/{}/tokens/{}', $serverId, $tokenId), idempotent: true),
            Token::fromWire(...),
        );
    }

    /**
     * The key stops working at once. A server's last live key cannot be
     * revoked, so a server is never locked out. A revoke repeated after a
     * lost answer whose first attempt took effect throws `not_found`.
     *
     * ```php
     * $account->servers->tokens->revoke($serverId, $tokenId);
     * ```
     *
     * @throws ConflictException it is the server's last live key (`last_token`)
     * @throws NotFoundException the account has no such server or key
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an id is empty, `.` or `..`; nothing was sent
     */
    public function revoke(string $serverId, string $tokenId): void
    {
        $this->caller->nothing(new Call(
            'DELETE',
            Wire::path('/v1/servers/{}/tokens/{}', $serverId, $tokenId),
            idempotent: true,
            expect: Expect::Nothing,
        ));
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
