<?php

declare(strict_types=1);

namespace Sendora;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Internal\Caller;
use Sendora\Internal\CurlTransport;
use Sendora\Internal\Options;
use Sendora\Internal\Psr18Transport;
use Sendora\Internal\SystemClock;
use Sendora\Resources\Domains;
use Sendora\Resources\Erasures;
use Sendora\Resources\Servers;

/**
 * The client for an account key (`ak_…`): the account's servers and their
 * keys, its sending domains and its erasures. An account key manages the
 * account and never sends; a server key on this client is refused with
 * `wrong_token_kind`.
 *
 * One client per process is the intended use. The client needs no closing.
 *
 * ```php
 * $account = new SendoraAccount(); // the key from SENDORA_ACCOUNT_TOKEN
 * ```
 */
final class SendoraAccount
{
    private const KEY_VARIABLE = 'SENDORA_ACCOUNT_TOKEN';

    /** Adds, checks and removes the domains that the account sends from. */
    public readonly Domains $domains;

    /** Erases a person from every server of the account. */
    public readonly Erasures $erasures;

    /** Creates, reads, renames and removes the account's servers, and manages each server's keys. */
    public readonly Servers $servers;

    private readonly Options $options;

    private readonly CurlTransport|Psr18Transport $transport;

    private readonly Caller $caller;

    /**
     * Makes the client. Nothing is sent until a call.
     *
     * @param null|string $token the account key; `SENDORA_ACCOUNT_TOKEN` when not given
     * @param null|string $baseUrl the API's address; `SENDORA_BASE_URL`, then https://api.sendora.se, when not given
     * @param float $timeout the seconds a whole attempt may take over curl; a PSR-18 client keeps its own
     * @param int $maxRetries how many times a failed call is repeated when repeating is safe; 0 turns retries off
     * @param null|ClientInterface $httpClient a PSR-18 client of your own, for a proxy, a private CA or tests
     * @param null|RequestFactoryInterface $requestFactory builds the client's requests; the client itself when it is one
     * @param null|StreamFactoryInterface $streamFactory builds the requests' bodies; the client itself when it is one
     * @param null|LoggerInterface $logger records each retry at debug level
     *
     * @throws InvalidArgumentException a setting is missing or out of range; the message never holds the key
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $token = null,
        ?string $baseUrl = null,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        $options = Options::resolve($token, $baseUrl, $timeout, $maxRetries, self::KEY_VARIABLE);
        $transport = $httpClient === null
            ? CurlTransport::open($options->key, $options->baseUrl, $options->timeout)
            : Psr18Transport::over($httpClient, $requestFactory, $streamFactory, $options->key, $options->baseUrl);
        $jitter = static fn(): float => \random_int(0, 999_999) / 1_000_000;
        $this->assemble($options, $transport, new Caller($transport, $options->baseUrl, $options->maxRetries, new SystemClock(), $jitter, $logger));
    }

    /**
     * A copy with another timeout or retry count, over the same connection,
     * for one slow call or a job that must not retry.
     *
     * @throws InvalidArgumentException
     */
    public function withOptions(?float $timeout = null, ?int $maxRetries = null): self
    {
        $options = $this->options->with($timeout, $maxRetries);
        $transport = $this->transport instanceof CurlTransport ? $this->transport->withTimeout($options->timeout) : $this->transport;
        // PHP 8.2 cannot set a clone's readonly properties, so the copy is made without the constructor.
        $copy = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $copy->assemble($options, $transport, $this->caller->with($transport, $options->maxRetries));

        return $copy;
    }

    /**
     * Shows the address alone, never the key.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->options->baseUrl];
    }

    /** Sets every property once: the constructor's last step, and a copy's only one. */
    private function assemble(Options $options, CurlTransport|Psr18Transport $transport, Caller $caller): void
    {
        $this->options = $options;
        $this->transport = $transport;
        $this->caller = $caller;
        $this->domains = new Domains($caller);
        $this->erasures = new Erasures($caller);
        $this->servers = new Servers($caller);
    }
}
