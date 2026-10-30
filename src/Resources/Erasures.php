<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\BadRequestException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Wire;
use Sendora\Types\Erasure;

/**
 * Erases a person from every server of the account, as a request under
 * the right to erasure asks.
 *
 * @final
 */
class Erasures
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Asks Sendora to erase one person from every server of the account.
     * The content of every message to or from the address, sent and
     * received, is deleted, and the address becomes a pseudonym in the
     * message log. Later mail to or from the address is sent and received
     * as before. The answer is the pending erasure; read it by its id to
     * see the outcome. Erasing an address again changes nothing more, so
     * the call is repeated after a lost connection.
     *
     * ```php
     * $erasure = $account->erasures->create(address: 'anna@example.com');
     * echo $erasure->erasureId, ' ', $erasure->status, "\n";
     * ```
     *
     * @param string $address the person's e-mail address
     *
     * @throws BadRequestException the address is not an e-mail address (`invalid_request`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(
        #[\SensitiveParameter]
        string $address,
    ): Erasure {
        return $this->caller->json(
            new Call('POST', '/v1/erasures', idempotent: true, body: Wire::body(['address' => $address])),
            Erasure::fromWire(...),
        );
    }

    /**
     * One erasure by its id. Once it is `done`, it holds the outcome. A
     * done erasure can be read for 30 days.
     *
     * ```php
     * $erasure = $account->erasures->get($erasureId);
     * echo $erasure->status, ' ', $erasure->messages, "\n";
     * ```
     *
     * @throws NotFoundException the account has no erasure with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $erasureId): Erasure
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/erasures/{}', $erasureId), idempotent: true),
            Erasure::fromWire(...),
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
