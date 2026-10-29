<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\InvalidArgumentException;

/**
 * What a client takes besides its resources, each setting checked when the
 * client is made, so a nonsense value fails at startup.
 *
 * @internal
 */
final class Options
{
    public const DEFAULT_BASE_URL = 'https://api.sendora.se';

    private const WHITESPACE = " \t\n\r\x0B\x0C";

    /** @param \SensitiveParameterValue<string> $key */
    private function __construct(
        public readonly \SensitiveParameterValue $key,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly int $maxRetries,
    ) {}

    /**
     * The settings a client is made with, the key and the address read from
     * the environment when not given.
     *
     * @param string $keyVariable the variable that holds this kind of key
     *
     * @throws InvalidArgumentException a setting is missing or out of range; the message never holds the key
     */
    public static function resolve(
        #[\SensitiveParameter]
        ?string $token,
        ?string $baseUrl,
        float $timeout,
        int $maxRetries,
        string $keyVariable,
    ): self {
        return new self(
            new \SensitiveParameterValue(self::key($token, $keyVariable)),
            self::address($baseUrl ?? self::variable('SENDORA_BASE_URL') ?? self::DEFAULT_BASE_URL),
            self::timeout($timeout),
            self::maxRetries($maxRetries),
        );
    }

    /**
     * The same settings with another timeout or retry count, each checked.
     *
     * @throws InvalidArgumentException
     */
    public function with(?float $timeout, ?int $maxRetries): self
    {
        return new self(
            $this->key,
            $this->baseUrl,
            self::timeout($timeout ?? $this->timeout),
            self::maxRetries($maxRetries ?? $this->maxRetries),
        );
    }

    /**
     * A variable's value from `$_SERVER`, then `$_ENV`, then the process's
     * environment. Frameworks' dotenv loaders fill the first two and not
     * always the third. An empty value counts as unset.
     */
    private static function variable(string $name): ?string
    {
        foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null, \getenv($name)] as $value) {
            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function key(#[\SensitiveParameter] ?string $token, string $variable): string
    {
        $key = \trim($token ?? self::variable($variable) ?? '', self::WHITESPACE);
        if ($key === '') {
            throw new InvalidArgumentException("No key was given: pass token, or set {$variable}. Keys are made in the dashboard.");
        }
        if (\preg_match('/^[A-Za-z0-9_-]+\z/', $key) !== 1) {
            $source = $token === null ? $variable : 'token';

            throw new InvalidArgumentException("The key in {$source} holds a character that no Sendora key has. Check that it was copied whole.");
        }

        return $key;
    }

    private static function address(string $baseUrl): string
    {
        $parsed = \preg_match('/[\x00-\x20\x7F]/', $baseUrl) === 1 ? [] : \parse_url($baseUrl);
        $parts = \is_array($parsed) ? $parsed : [];
        $scheme = \strtolower($parts['scheme'] ?? '');
        $host = \strtolower($parts['host'] ?? '');
        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('baseUrl must be an https address with a host, such as ' . self::DEFAULT_BASE_URL . '.');
        }
        // PHP sets the user, empty or not, whenever the address holds a password.
        if (isset($parts['user'])) {
            throw new InvalidArgumentException('baseUrl may not hold a user or a password.');
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('baseUrl may not hold a query or a fragment.');
        }
        if ($scheme === 'http' && !self::loopback($host)) {
            throw new InvalidArgumentException(
                'baseUrl may use http only for a loopback address, such as http://127.0.0.1:3000, so the key never crosses a network in clear.',
            );
        }

        return \rtrim($baseUrl, '/');
    }

    private static function loopback(string $host): bool
    {
        return $host === 'localhost'
            || $host === '[::1]'
            || (\str_starts_with($host, '127.') && \ip2long($host) !== false);
    }

    private static function timeout(float $timeout): float
    {
        if (!\is_finite($timeout) || $timeout <= 0.0) {
            throw new InvalidArgumentException('timeout must be a positive number of seconds.');
        }

        return $timeout;
    }

    private static function maxRetries(int $maxRetries): int
    {
        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must be a whole number of zero or more.');
        }

        return $maxRetries;
    }
}
