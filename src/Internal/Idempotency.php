<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\InvalidArgumentException;

/**
 * The key a send goes under, which makes repeating the send safe: the API
 * answers a repeat under the same key with the first answer.
 *
 * @internal
 */
final class Idempotency
{
    /**
     * The caller's key, or a new version 4 UUID. Either is made once per
     * call, so every attempt repeats it.
     *
     * @throws InvalidArgumentException the key cannot go into an HTTP header
     */
    public static function keyFor(?string $given): string
    {
        if ($given === null) {
            return self::newKey();
        }
        self::latin1($given);

        return $given;
    }

    /**
     * The key as the Latin-1 bytes a header carries, as fetch sends it. A
     * control character would end the header or start another, since curl
     * sends a header's bytes unchecked, so it is refused, as the API refuses
     * it. So is a character beyond U+00FF, which Latin-1 lacks.
     *
     * @throws InvalidArgumentException the key cannot go into an HTTP header
     */
    public static function latin1(string $key): string
    {
        if (\preg_match('//u', $key) !== 1) {
            throw new InvalidArgumentException('An idempotency key is not valid UTF-8.');
        }
        if (\preg_match('/[\x{00}-\x{1F}\x{7F}-\x{9F}]|[^\x{00}-\x{FF}]/u', $key) === 1) {
            throw new InvalidArgumentException(
                'An idempotency key may not hold a control character, such as a line break or a tab, '
                . 'or a character beyond U+00FF, which an HTTP header cannot carry.',
            );
        }
        // What is left is ASCII and the two-byte UTF-8 of U+0080 to U+00FF.
        $bytes = '';
        $length = \strlen($key);
        for ($at = 0; $at < $length; ++$at) {
            $byte = \ord($key[$at]);
            $bytes .= $byte < 0x80 ? $key[$at] : \chr((($byte & 0x1F) << 6) | (\ord($key[++$at]) & 0x3F));
        }

        return $bytes;
    }

    private static function newKey(): string
    {
        $bytes = \random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split(\bin2hex($bytes), 4));
    }
}
