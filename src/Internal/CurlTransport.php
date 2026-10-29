<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\ApiConnectionException;
use Sendora\Exception\ApiTimeoutException;
use Sendora\Types\ErrorCode;
use Sendora\Version;

/**
 * The default transport, and the only class that calls curl. One handle per
 * client, reset between calls, keeps the connection open, so a long-running
 * worker reuses it.
 *
 * @internal
 */
final class CurlTransport implements Transport
{
    /** curl's error numbers for a certificate that fails. */
    private const CERTIFICATE = [58, 60, 77, 83];

    /** curl's error numbers for a scheme or an address that curl cannot use. */
    private const UNSENDABLE = [1, 3];

    /** curl's error numbers for an answer that cannot be read or decoded. */
    private const UNREADABLE = [23, 61];

    /** @param \SensitiveParameterValue<string> $key */
    private function __construct(
        private readonly \CurlHandle $handle,
        private readonly \SensitiveParameterValue $key,
        private readonly string $baseUrl,
        private readonly float $timeout,
        private readonly Platform $platform,
    ) {}

    /** @param \SensitiveParameterValue<string> $key */
    public static function open(\SensitiveParameterValue $key, string $baseUrl, float $timeout, ?Platform $platform = null): self
    {
        return new self(\curl_init(), $key, $baseUrl, $timeout, $platform ?? Platform::ofThisProcess());
    }

    /** The same transport over the same connection, with another timeout. */
    public function withTimeout(float $timeout): self
    {
        return new self($this->handle, $this->key, $this->baseUrl, $timeout, $this->platform);
    }

    public function send(#[\SensitiveParameter] Call $call): Reply
    {
        $headers = [];
        // Each status line starts an answer, so an interim 100 Continue never lends the final answer its headers.
        $onHeader = static function (\CurlHandle $handle, string $line) use (&$headers): int {
            if (\str_starts_with($line, 'HTTP/')) {
                $headers = [];
            } elseif (\str_contains($line, ':')) {
                [$name, $value] = \explode(':', $line, 2);
                $headers[\strtolower(\trim($name))] = \trim($value);
            }

            return \strlen($line);
        };

        \curl_reset($this->handle);
        \curl_setopt_array($this->handle, $this->options($call, $onHeader));
        $body = \curl_exec($this->handle);
        if (!\is_string($body)) {
            throw self::failure(\curl_errno($this->handle), \curl_error($this->handle), $this->timeout);
        }

        return new Reply(\curl_getinfo($this->handle, \CURLINFO_RESPONSE_CODE), $headers, $body);
    }

    /**
     * Everything curl is told for one call.
     *
     * @param \Closure(\CurlHandle, string): int $onHeader
     *
     * @return array<int, mixed>
     */
    public function options(#[\SensitiveParameter] Call $call, \Closure $onHeader): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->key->getValue(),
            'Accept: ' . ($call->expect === Expect::Bytes ? '*/*' : 'application/json'),
            'User-Agent: sendora-php/' . Version::VERSION,
        ];
        $options = [
            \CURLOPT_URL => $this->baseUrl . $call->path . ($call->query === [] ? '' : '?' . \http_build_query($call->query, '', '&', \PHP_QUERY_RFC3986)),
            \CURLOPT_CUSTOMREQUEST => $call->method,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADERFUNCTION => $onHeader,
            \CURLOPT_ACCEPT_ENCODING => '',
            \CURLOPT_TIMEOUT_MS => (int) \ceil($this->timeout * 1000),
            // curl would time out a DNS lookup with SIGALRM, which Laravel's queue worker keeps for its own timeouts.
            \CURLOPT_NOSIGNAL => true,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_SUPPRESS_CONNECT_HEADERS => true,
        ];
        if ($call->body !== null) {
            $options[\CURLOPT_POSTFIELDS] = $call->body;
            $headers[] = 'Content-Type: application/json';
        } elseif ($call->method === 'POST') {
            // Fastify refuses an empty body labelled JSON, and curl would label it a form.
            $options[\CURLOPT_POSTFIELDS] = '';
            $headers[] = 'Content-Type:';
        }
        if ($call->idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . Idempotency::latin1($call->idempotencyKey);
        }
        $options[\CURLOPT_HTTPHEADER] = $headers;

        return $options + self::tls($this->platform);
    }

    /**
     * Windows' PHP ships no certificate file, so curl would trust nothing
     * there until `php.ini` names one. Windows' own store stands in.
     *
     * @return array<int, int>
     */
    public static function tls(Platform $platform): array
    {
        return $platform->windows && !$platform->caConfigured && $platform->nativeCa !== null
            ? [\CURLOPT_SSL_OPTIONS => $platform->nativeCa]
            : [];
    }

    /** What a call that got no answer throws, by curl's error number. curl's own message names the host, never the key. */
    public static function failure(int $errno, string $detail, float $timeout): ApiConnectionException
    {
        $reported = " curl reported error {$errno}: {$detail}";
        $failed = ErrorCode::ConnectionFailed->value;

        return match (true) {
            $errno === \CURLE_OPERATION_TIMEDOUT => new ApiTimeoutException("The API did not answer within {$timeout} seconds.{$reported}", ErrorCode::Timeout->value),
            \in_array($errno, self::CERTIFICATE, true) => new ApiConnectionException(
                "The API's certificate could not be verified. Check the operating system's certificate store, "
                . "and curl.cainfo and openssl.cafile in php.ini.{$reported}",
                $failed,
                final: true,
            ),
            \in_array($errno, self::UNSENDABLE, true) => new ApiConnectionException(
                "The request could not be sent as it stands, and sending it again would fail the same way.{$reported}",
                $failed,
                final: true,
            ),
            \in_array($errno, self::UNREADABLE, true) => new ApiConnectionException(
                "The API answered, but its answer could not be read or decoded. The call may have taken effect.{$reported}",
                $failed,
                final: true,
            ),
            default => new ApiConnectionException("The connection to the API failed.{$reported}", $failed),
        };
    }
}
