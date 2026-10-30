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
use Sendora\Types\DomainList;
use Sendora\Types\SendingDomain;
use Sendora\Types\VerifiedDomain;

/**
 * Adds, checks and removes the domains that the account sends from.
 *
 * @final
 */
class Domains
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Adds a domain with its own DKIM key, and returns the two records to
     * create: a CNAME for the return path and a TXT record for the DKIM
     * key. Mail may go out once both are seen. A create is never repeated
     * after a lost connection, which may have added the domain.
     *
     * ```php
     * $added = $account->domains->create(domain: 'example.se');
     * echo $added->returnPath->host, ' ', $added->returnPath->value, "\n";
     * echo $added->dkim->host, ' ', $added->dkim->value, "\n";
     * ```
     *
     * @param string $domain the domain that mail is sent from, such as `example.se`
     *
     * @throws ConflictException the account has the domain (`domain_exists`, with `existingId`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(string $domain): SendingDomain
    {
        return $this->caller->json(
            new Call('POST', '/v1/domains', idempotent: false, body: Wire::body(['domain' => $domain])),
            SendingDomain::fromWire(...),
        );
    }

    /**
     * Every sending domain of the account.
     *
     * ```php
     * foreach ($account->domains->list()->domains as $domain) {
     *     echo $domain->domain, ' ', $domain->verified ? 'verified' : 'waiting', "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): DomainList
    {
        return $this->caller->json(
            new Call('GET', '/v1/domains', idempotent: true),
            DomainList::fromWire(...),
        );
    }

    /**
     * One domain by its id, with the state of its records.
     *
     * ```php
     * $domain = $account->domains->get($domainId);
     * ```
     *
     * @throws NotFoundException the account has no domain with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $domainId): SendingDomain
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/domains/{}', $domainId), idempotent: true),
            SendingDomain::fromWire(...),
        );
    }

    /**
     * Looks both records up now, and returns the domain as it stands with
     * what each lookup found in `check`: `ok`, `missing`, `mismatch` or
     * `dns_error`. A `dns_error` changes nothing, and a verified domain
     * whose record is found missing stays verified for 24 hours
     * (`failingSince`).
     *
     * ```php
     * $checked = $account->domains->verify($domainId);
     * echo $checked->check->returnPath, ' ', $checked->check->dkim, "\n";
     * ```
     *
     * @throws NotFoundException the account has no domain with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function verify(string $domainId): VerifiedDomain
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/domains/{}/verify', $domainId), idempotent: true),
            VerifiedDomain::fromWire(...),
        );
    }

    /**
     * Removes the domain. Mail from it is refused from then, and its DKIM
     * key is gone. A delete repeated after a lost answer whose first
     * attempt took effect throws `not_found`.
     *
     * ```php
     * $account->domains->delete($domainId);
     * ```
     *
     * @throws NotFoundException the account has no domain with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function delete(string $domainId): void
    {
        $this->caller->nothing(new Call('DELETE', Wire::path('/v1/domains/{}', $domainId), idempotent: true, expect: Expect::Nothing));
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
