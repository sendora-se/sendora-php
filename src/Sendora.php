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
use Sendora\Resources\Broadcasts;
use Sendora\Resources\Email;
use Sendora\Resources\Messages;
use Sendora\Resources\Streams;
use Sendora\Resources\Suppressions;
use Sendora\Resources\ThisServer;
use Sendora\Resources\Tokens;
use Sendora\Resources\Webhooks;

/**
 * The client for a server key (`sk_…`): everything inside one server.
 *
 * One client per process is the intended use. A long-running worker, such
 * as Laravel's queue worker, then reuses its connection. The client needs
 * no closing.
 *
 * ```php
 * $sendora = new Sendora(); // the key from SENDORA_API_TOKEN
 * ```
 */
final class Sendora
{
    private const KEY_VARIABLE = 'SENDORA_API_TOKEN';

    /** Sends broadcasts on the server's broadcast streams, and follows and cancels them. */
    public readonly Broadcasts $broadcasts;

    /** Sends mail: one message, or up to 100 in a batch. */
    public readonly Email $email;

    /** Reads the message log: one message with its timeline, or a search. */
    public readonly Messages $messages;

    /** Reads the key's own server: its mode, its limits and the domains it may send from. */
    public readonly ThisServer $server;

    /** Adds, reads, renames and archives the server's streams. */
    public readonly Streams $streams;

    /** Reads each stream's suppression list, and lifts an entry from one. */
    public readonly Suppressions $suppressions;

    /** Creates, reads and revokes the server's keys. */
    public readonly Tokens $tokens;

    /** Registers the server's webhooks, reads their deliveries and rolls their secrets. */
    public readonly Webhooks $webhooks;

    private readonly Options $options;

    private readonly CurlTransport|Psr18Transport $transport;

    private readonly Caller $caller;

    /**
     * Makes the client. Nothing is sent until a call.
     *
     * @param null|string $token the server key; `SENDORA_API_TOKEN` when not given
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
        $this->broadcasts = new Broadcasts($caller);
        $this->email = new Email($caller);
        $this->messages = new Messages($caller);
        $this->server = new ThisServer($caller);
        $this->streams = new Streams($caller);
        $this->suppressions = new Suppressions($caller);
        $this->tokens = new Tokens($caller);
        $this->webhooks = new Webhooks($caller);
    }
}
