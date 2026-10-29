<?php

declare(strict_types=1);

namespace Sendora\Resources;

use Sendora\Exception\BadRequestException;
use Sendora\Exception\ConflictException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Exception\NotFoundException;
use Sendora\Exception\SendoraException;
use Sendora\Exception\UnprocessableEntityException;
use Sendora\Internal\Call;
use Sendora\Internal\Caller;
use Sendora\Internal\Expect;
use Sendora\Internal\Wire;
use Sendora\Types\InboundDomain;
use Sendora\Types\InboundDomainList;
use Sendora\Types\VerifiedInboundDomain;

/**
 * Claims, checks and removes the domains that the server's inbound
 * streams receive on.
 *
 * @final
 */
class InboundDomains
{
    /** @internal */
    public function __construct(
        private readonly Caller $caller,
    ) {}

    /**
     * Claims a domain for an inbound stream, and returns the two records
     * to create: an MX record that brings the domain's mail to Sendora,
     * and a TXT record that proves the claim. Mail is accepted once both
     * are seen. A create is never repeated after a lost connection, which
     * may have claimed the domain.
     *
     * ```php
     * $domain = $sendora->inboundDomains->create(streamId: $inboundStreamId, domain: 'post.example.se');
     * echo $domain->mx->value, ' ', $domain->txt->host, ' ', $domain->txt->value, "\n";
     * ```
     *
     * @param string $streamId an inbound stream of the server
     * @param string $domain the domain to receive on, such as `post.example.se`
     *
     * @throws BadRequestException the name is Sendora's own (`domain_reserved`)
     * @throws ConflictException the stream has a domain, or another account holds the name verified (`inbound_domain_exists`, with `existingId` for the stream's own)
     * @throws UnprocessableEntityException no inbound stream of this server has the id, or it is archived (`stream_not_found`, `stream_archived`)
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException an argument cannot be sent; nothing was sent
     */
    public function create(string $streamId, string $domain): InboundDomain
    {
        return $this->caller->json(
            new Call('POST', '/v1/inbound/domains', idempotent: false, body: Wire::body(['streamId' => $streamId, 'domain' => $domain])),
            InboundDomain::fromWire(...),
        );
    }

    /**
     * Every inbound domain of the server.
     *
     * ```php
     * foreach ($sendora->inboundDomains->list()->domains as $domain) {
     *     echo $domain->domain, ' ', $domain->verified ? 'verified' : 'waiting', "\n";
     * }
     * ```
     *
     * @throws SendoraException the call failed
     */
    public function list(): InboundDomainList
    {
        return $this->caller->json(
            new Call('GET', '/v1/inbound/domains', idempotent: true),
            InboundDomainList::fromWire(...),
        );
    }

    /**
     * One domain by its id, with the state of its records.
     *
     * ```php
     * $domain = $sendora->inboundDomains->get($inboundDomainId);
     * ```
     *
     * @throws NotFoundException no inbound domain of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function get(string $inboundDomainId): InboundDomain
    {
        return $this->caller->json(
            new Call('GET', Wire::path('/v1/inbound/domains/{}', $inboundDomainId), idempotent: true),
            InboundDomain::fromWire(...),
        );
    }

    /**
     * Looks both records up now, and returns the domain as it stands with
     * what each lookup found in `check`: `ok`, `missing`, `mismatch` or
     * `dns_error`.
     *
     * ```php
     * $checked = $sendora->inboundDomains->verify($inboundDomainId);
     * echo $checked->check->mx, ' ', $checked->check->txt, "\n";
     * ```
     *
     * @throws NotFoundException no inbound domain of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function verify(string $inboundDomainId): VerifiedInboundDomain
    {
        return $this->caller->json(
            new Call('POST', Wire::path('/v1/inbound/domains/{}/verify', $inboundDomainId), idempotent: true),
            VerifiedInboundDomain::fromWire(...),
        );
    }

    /**
     * Removes the domain. Mail to it is refused from then, and the name is
     * free again. A delete repeated after a lost answer whose first attempt
     * took effect throws `not_found`.
     *
     * ```php
     * $sendora->inboundDomains->delete($inboundDomainId);
     * ```
     *
     * @throws NotFoundException no inbound domain of this server has the id
     * @throws SendoraException the call failed
     * @throws InvalidArgumentException the id is empty, `.` or `..`; nothing was sent
     */
    public function delete(string $inboundDomainId): void
    {
        $this->caller->nothing(new Call('DELETE', Wire::path('/v1/inbound/domains/{}', $inboundDomainId), idempotent: true, expect: Expect::Nothing));
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
