<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\ResponseValidationException;
use Sendora\Types\ErrorCode;

/**
 * The decoder's type guards. Each reads one key of a JSON object and
 * throws `ResponseValidationException` when the key is missing or holds
 * the wrong JSON type. Its message names the key, never the value, which
 * may be a customer's data.
 *
 * @internal
 */
final class Json
{
    /** RFC 3339's form. PHP's own parser also takes phrases such as `next monday`. */
    private const TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/';

    public static function string(#[\SensitiveParameter] JsonObject $json, string $key): string
    {
        $value = self::value($json, $key);
        if (!\is_string($value)) {
            throw self::wrong($json, $key, 'a string');
        }

        return $value;
    }

    public static function nullableString(#[\SensitiveParameter] JsonObject $json, string $key): ?string
    {
        $value = self::value($json, $key);
        if ($value !== null && !\is_string($value)) {
            throw self::wrong($json, $key, 'a string or null');
        }

        return $value;
    }

    /** A JSON integer alone: `true`, a float and an integer too large for PHP are refused. */
    public static function int(#[\SensitiveParameter] JsonObject $json, string $key): int
    {
        $value = self::value($json, $key);
        if (!\is_int($value)) {
            throw self::wrong($json, $key, 'an integer');
        }

        return $value;
    }

    public static function nullableInt(#[\SensitiveParameter] JsonObject $json, string $key): ?int
    {
        $value = self::value($json, $key);
        if ($value !== null && !\is_int($value)) {
            throw self::wrong($json, $key, 'an integer or null');
        }

        return $value;
    }

    /**
     * A string from a fixed set, such as a union's discriminator, which the
     * decoder cannot read past when it holds another value.
     *
     * @template T of string
     *
     * @param non-empty-list<T> $known
     *
     * @return T
     */
    public static function choice(#[\SensitiveParameter] JsonObject $json, string $key, array $known): string
    {
        $value = self::value($json, $key);
        if (!\in_array($value, $known, true)) {
            throw self::wrong($json, $key, 'one of ' . \implode(', ', $known));
        }

        return $value;
    }

    /**
     * A key that only some answers carry, read by the guard given when it
     * is there, and `null` when it is not.
     *
     * @template T
     *
     * @param \Closure(JsonObject, string): T $read
     *
     * @return null|T
     */
    public static function optional(#[\SensitiveParameter] JsonObject $json, string $key, \Closure $read): mixed
    {
        return \property_exists($json->object, $key) ? $read($json, $key) : null;
    }

    public static function bool(#[\SensitiveParameter] JsonObject $json, string $key): bool
    {
        $value = self::value($json, $key);
        if (!\is_bool($value)) {
            throw self::wrong($json, $key, 'a boolean');
        }

        return $value;
    }

    /** A time in RFC 3339's form, in UTC. */
    public static function time(#[\SensitiveParameter] JsonObject $json, string $key): \DateTimeImmutable
    {
        $value = self::value($json, $key);
        $time = \is_string($value) ? self::parseTime($value) : null;
        if ($time === null) {
            throw self::wrong($json, $key, 'a time in RFC 3339 form');
        }

        return $time;
    }

    public static function nullableTime(#[\SensitiveParameter] JsonObject $json, string $key): ?\DateTimeImmutable
    {
        return self::value($json, $key) === null ? null : self::time($json, $key);
    }

    public static function object(#[\SensitiveParameter] JsonObject $json, string $key): JsonObject
    {
        $value = self::value($json, $key);
        if (!$value instanceof \stdClass) {
            throw self::wrong($json, $key, 'an object');
        }

        return new JsonObject($value, self::where($json, $key));
    }

    /** @return list<JsonObject> */
    public static function objects(#[\SensitiveParameter] JsonObject $json, string $key): array
    {
        $objects = [];
        foreach (self::list($json, $key) as $index => $member) {
            if (!$member instanceof \stdClass) {
                throw self::wrong($json, "{$key}.{$index}", 'an object');
            }
            $objects[] = new JsonObject($member, self::where($json, "{$key}.{$index}"));
        }

        return $objects;
    }

    /** @return list<string> */
    public static function strings(#[\SensitiveParameter] JsonObject $json, string $key): array
    {
        $strings = [];
        foreach (self::list($json, $key) as $index => $member) {
            if (!\is_string($member)) {
                throw self::wrong($json, "{$key}.{$index}", 'a string');
            }
            $strings[] = $member;
        }

        return $strings;
    }

    /**
     * A map of the customer's own names, such as `metadata`: its keys kept
     * as they came, a decimal one as an integer, and its values strings.
     *
     * @return array<array-key, string>
     */
    public static function map(#[\SensitiveParameter] JsonObject $json, string $key): array
    {
        $value = self::value($json, $key);
        if (!$value instanceof \stdClass) {
            throw self::wrong($json, $key, 'an object');
        }
        $map = [];
        foreach (\get_object_vars($value) as $name => $member) {
            if (!\is_string($member)) {
                throw self::wrong($json, "{$key}.{$name}", 'a string');
            }
            $map[$name] = $member;
        }

        return $map;
    }

    /** The time a text names, in UTC, or `null` when the text is not in RFC 3339's form. */
    public static function parseTime(string $text): ?\DateTimeImmutable
    {
        if (\preg_match(self::TIME, $text) !== 1) {
            return null;
        }

        try {
            $time = new \DateTimeImmutable($text);
        } catch (\Exception) {
            return null;
        }
        // PHP takes a date such as 30 February and rolls it on, with a warning.
        if (\DateTimeImmutable::getLastErrors() !== false) {
            return null;
        }

        return $time->setTimezone(new \DateTimeZone('UTC'));
    }

    /** @return list<mixed> */
    private static function list(#[\SensitiveParameter] JsonObject $json, string $key): array
    {
        $value = self::value($json, $key);
        if (!\is_array($value) || !\array_is_list($value)) {
            throw self::wrong($json, $key, 'a list');
        }

        return $value;
    }

    private static function value(#[\SensitiveParameter] JsonObject $json, string $key): mixed
    {
        if (!\property_exists($json->object, $key)) {
            throw self::unreadable(self::where($json, $key) . ' is missing');
        }

        return $json->object->{$key};
    }

    private static function wrong(#[\SensitiveParameter] JsonObject $json, string $key, string $expected): ResponseValidationException
    {
        return self::unreadable(self::where($json, $key) . " is not {$expected}");
    }

    private static function where(#[\SensitiveParameter] JsonObject $json, string $key): string
    {
        return $json->path === '' ? $key : "{$json->path}.{$key}";
    }

    private static function unreadable(string $problem): ResponseValidationException
    {
        return new ResponseValidationException(
            "The API's answer could not be read, since {$problem}. The call took effect.",
            ErrorCode::UnexpectedResponse->value,
        );
    }
}
