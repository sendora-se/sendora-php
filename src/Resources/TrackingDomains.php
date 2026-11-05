<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\ConflictException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Exception\UnprocessableEntityException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Wire;
use Sendora\Types\TrackingDomain;
use Sendora\Types\TrackingDomainList;

/**
 * Adds, checks and removes the hosts that tracked links use.
 *
 * @final
 */
class TrackingDomains
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Claims a host under one of the account's verified sending domains,
     * and returns the CNAME record to add. A host whose records are not
     * right within 7 days goes. A create is never repeated after a lost
     * connection, which may have claimed the host.
     *
     * ```php
     * $added = $account->trackingDomains->create(host: 'links.example.se');
     * echo $added->cname->host, ' ', $added->cname->value, "\n";
     * ```
     *
     * @param string $host the host, such as `links.example.se`
     *
     * @throws ConflictException the account has the host or one on that sending domain (`tracking_domain_exists`, with `existingId`), another account holds it (`tracking_host_taken`), or the account has no room (`tracking_domain_limit`)
     * @throws UnprocessableEntityException the host is not under a verified sending domain, or is one itself
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(string $host): TrackingDomain
    {
        return $this->caller->json(
            new Call('POST', '/v1/tracking-domains', idempotent: false, body: Wire::body(['host' => $host])),
            TrackingDomain::fromWire(...),
        );
    }

    /**
     * Every tracking domain of the account.
     *
     * ```php
     * foreach ($account->trackingDomains->list()->trackingDomains as $domain) {
     *     echo $domain->host, ' ', $domain->state, "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): TrackingDomainList
    {
        return $this->caller->json(
            new Call('GET', '/v1/tracking-domains', idempotent: true),
            TrackingDomainList::fromWire(...),
        );
    }

    /**
     * One tracking domain by its id, with what the last check found.
     *
     * ```php
     * $domain = $account->trackingDomains->get($trackingDomainId);
     * ```
     *
     * @throws NotFoundException the account has no tracking domain with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $trackingDomainId): TrackingDomain
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/tracking-domains/{}', $trackingDomainId), idempotent: true),
            TrackingDomain::fromWire(...),
        );
    }

    /**
     * Looks the CNAME and the CAA records up now, and returns the tracking
     * domain as it stands. A `dns_error` changes nothing.
     *
     * ```php
     * $checked = $account->trackingDomains->verify($trackingDomainId);
     * echo $checked->state, ' ', $checked->cname->check ?? '', "\n";
     * ```
     *
     * @throws NotFoundException the account has no tracking domain with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function verify(string $trackingDomainId): TrackingDomain
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/tracking-domains/{}/verify', $trackingDomainId), idempotent: true),
            TrackingDomain::fromWire(...),
        );
    }

    /**
     * Removes the tracking domain. New mail stops using it at once, and
     * links in sent mail keep working while the CNAME record points at
     * Sendora. A delete repeated after a lost answer whose first attempt
     * took effect throws `not_found`.
     *
     * ```php
     * $account->trackingDomains->delete($trackingDomainId);
     * ```
     *
     * @throws NotFoundException the account has no tracking domain with the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function delete(string $trackingDomainId): void
    {
        $this->caller->nothing(new Call('DELETE', Wire::path('/v1/tracking-domains/{}', $trackingDomainId), idempotent: true, expect: Expect::Nothing));
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
