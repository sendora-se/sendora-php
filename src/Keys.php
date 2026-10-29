<?php

declare(strict_types=1);

namespace Sendora;

/** Tells a test server's key from a live one's. */
final class Keys
{
    private const TEST_KEY_PREFIX = 'sk_test_';

    /**
     * Whether a key belongs to a test server, whose mail goes through
     * everything but delivery and so reaches nobody. Check a key at startup
     * to refuse a test key where mail must arrive, and the other way round
     * in CI.
     *
     * ```php
     * if ($production && Keys::isTestKey($key)) {
     *     throw new RuntimeException("A test server's key delivers nothing.");
     * }
     * ```
     *
     * @param string $token a server key
     */
    public static function isTestKey(#[\SensitiveParameter] string $token): bool
    {
        return \str_starts_with($token, self::TEST_KEY_PREFIX);
    }
}
