<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * What the certificate rule reads of the machine, apart so that the tests
 * can stand in for Windows.
 *
 * @internal
 */
final class Platform
{
    /**
     * @param bool $windows whether PHP runs on Windows
     * @param bool $caConfigured whether `php.ini` sets `curl.cainfo` or `openssl.cafile`
     * @param null|int $nativeCa `CURLSSLOPT_NATIVE_CA`, which a PHP built against libcurl 7.71 or newer has
     */
    public function __construct(
        public readonly bool $windows,
        public readonly bool $caConfigured,
        public readonly ?int $nativeCa,
    ) {}

    public static function ofThisProcess(): self
    {
        return new self(
            \PHP_OS_FAMILY === 'Windows',
            \ini_get('curl.cainfo') . \ini_get('openssl.cafile') !== '',
            \defined('CURLSSLOPT_NATIVE_CA') ? \CURLSSLOPT_NATIVE_CA : null, // @codeCoverageIgnore
        );
    }
}
