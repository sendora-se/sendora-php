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
 * Creates, reads and revokes the keys of the key's own server. Every key
 * of a server has the same rights.
 *
 * @final
 */
class Tokens
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Creates another key and returns its value, shown this once. A server
     * holds at most two live keys, so a rotation is create, switch,
     * revoke. A create is never repeated after a lost connection, which may
     * have created the key.
     *
     * ```php
     * $created = $sendora->tokens->create(name: 'Fakturasystemet');
     * $vault->store('sendora', $created->token);
     * ```
     *
     * @param string $name 1 to 100 characters, for people to tell keys apart
     *
     * @throws ConflictException the server holds two live keys (`token_limit`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(string $name): CreatedToken
    {
        return $this->caller->json(
            new Call('POST', '/v1/tokens', idempotent: false, body: Wire::body(['name' => $name])),
            CreatedToken::fromWire(...),
        );
    }

    /**
     * Every key of the server, revoked ones included, without their values.
     *
     * ```php
     * foreach ($sendora->tokens->list()->tokens as $key) {
     *     echo $key->name, ' ', $key->prefix, "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): TokenList
    {
        return $this->caller->json(
            new Call('GET', '/v1/tokens', idempotent: true),
            TokenList::fromWire(...),
        );
    }

    /**
     * One key by its id. Its value is never part of the answer.
     *
     * ```php
     * $key = $sendora->tokens->get($tokenId);
     * ```
     *
     * @throws NotFoundException no key of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $tokenId): Token
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/tokens/{}', $tokenId), idempotent: true),
            Token::fromWire(...),
        );
    }

    /**
     * The key stops working at once. The server's last live key cannot be
     * revoked, so a server is never locked out. A revoke repeated after a
     * lost answer whose first attempt took effect throws `not_found`.
     *
     * ```php
     * $sendora->tokens->revoke($tokenId);
     * ```
     *
     * @throws ConflictException it is the server's last live key (`last_token`)
     * @throws NotFoundException no key of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function revoke(string $tokenId): void
    {
        $this->caller->nothing(new Call('DELETE', Wire::path('/v1/tokens/{}', $tokenId), idempotent: true, expect: Expect::Nothing));
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
