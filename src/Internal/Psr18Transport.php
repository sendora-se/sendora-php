<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sendora\Exception\ApiConnectionException;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Types\ErrorCode;
use Sendora\Version;

/**
 * Sends through a customer's PSR-18 client, which lends its proxy,
 * certificates, tracing and timeouts. The SDK still builds every request
 * itself, with its own headers.
 *
 * @internal
 */
final class Psr18Transport implements Transport
{
    /** @param \SensitiveParameterValue<string> $key */
    private function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly \SensitiveParameterValue $key,
        private readonly string $baseUrl,
    ) {}

    /**
     * The transport over a client, each factory defaulting to the client
     * when it is one, as Symfony's `Psr18Client` is.
     *
     * @param \SensitiveParameterValue<string> $key
     *
     * @throws InvalidArgumentException a factory is missing
     */
    public static function over(
        ClientInterface $client,
        ?RequestFactoryInterface $requests,
        ?StreamFactoryInterface $streams,
        \SensitiveParameterValue $key,
        string $baseUrl,
    ): self {
        return new self(
            $client,
            $requests ?? ($client instanceof RequestFactoryInterface ? $client : throw self::missing('requestFactory', 'request')),
            $streams ?? ($client instanceof StreamFactoryInterface ? $client : throw self::missing('streamFactory', 'stream')),
            $key,
            $baseUrl,
        );
    }

    public function send(#[\SensitiveParameter] Call $call): Reply
    {
        // The client's exception holds its request, whose Authorization header holds the key, so only its
        // class and message go on, and never as an argument a trace would keep.
        try {
            $response = $this->client->sendRequest($this->request($call));
        } catch (NetworkExceptionInterface $error) {
            throw self::failure('The connection to the API failed', $error::class . ': ' . $error->getMessage(), final: false);
        } catch (ClientExceptionInterface $error) {
            throw self::failure(
                'The HTTP client could not send the request, and would fail the same way again',
                $error::class . ': ' . $error->getMessage(),
                final: true,
            );
        }

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[\strtolower((string) $name)] = \implode(', ', $values);
        }

        return new Reply($response->getStatusCode(), $headers, (string) $response->getBody());
    }

    private function request(#[\SensitiveParameter] Call $call): RequestInterface
    {
        $url = $this->baseUrl . $call->path . ($call->query === [] ? '' : '?' . \http_build_query($call->query, '', '&', \PHP_QUERY_RFC3986));
        // The client decodes what it asks for, so the SDK leaves Accept-Encoding to it.
        $request = $this->requests->createRequest($call->method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->key->getValue())
            ->withHeader('Accept', $call->expect === Expect::Bytes ? '*/*' : 'application/json')
            ->withHeader('User-Agent', 'sendora-php/' . Version::VERSION);
        if ($call->body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json')->withBody($this->streams->createStream($call->body));
        } elseif ($call->method === 'POST') {
            $request = $request->withHeader('Content-Length', '0');
        }
        if ($call->idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', Idempotency::latin1($call->idempotencyKey));
        }

        return $request;
    }

    private static function failure(string $what, string $cause, bool $final): ApiConnectionException
    {
        return new ApiConnectionException("{$what}: {$cause}", ErrorCode::ConnectionFailed->value, final: $final);
    }

    private static function missing(string $argument, string $kind): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "{$argument} is required, since the httpClient is no PSR-17 {$kind} factory itself. "
            . "Pass one, such as Guzzle's GuzzleHttp\\Psr7\\HttpFactory.",
        );
    }
}
