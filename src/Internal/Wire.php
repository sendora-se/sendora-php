<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Attachment;
use Sendora\Exception\InvalidArgumentException;
use Sendora\Omit;

/**
 * A request's body and path as the API takes them.
 *
 * - A body is a JSON object, `{}` when every argument is left out. A
 *   batch's body is a list of such objects.
 * - Under `metadata`, `headers` and `substitutions` sit the customer's own
 *   names. Each such map is written as an object, `{}` when empty, so a
 *   decimal key such as `'2026'`, which PHP keeps as an integer, reaches
 *   the API as the string it was.
 * - Any other array whose keys are all integers is a list, written with
 *   `array_values()`, so a list with gaps is still a list.
 * - Inside the body, an object's `null` member is left out, as a left-out
 *   argument is.
 *
 * @internal
 */
final class Wire
{
    private const VERBATIM = ['metadata', 'headers', 'substitutions'];

    /**
     * A request's body as JSON text. An argument that is `null` or
     * `Omit::Omit` is left out, and `Literal::Null` is sent as `null`.
     *
     * @param array<string, mixed> $arguments
     *
     * @throws InvalidArgumentException a string is not UTF-8, or a value cannot be written as JSON
     */
    public static function body(#[\SensitiveParameter] array $arguments): string
    {
        $body = new \stdClass();
        foreach ($arguments as $name => $value) {
            if ($value === null || $value === Omit::Omit) {
                continue;
            }
            $body->{$name} = $value === Literal::Null ? null : self::value($name, $value);
        }

        return self::encode($body);
    }

    /**
     * A body that is a list of objects, as a batch's is. Each item is
     * written as an object, its `null` members left out.
     *
     * @param array<array-key, array<string, mixed>> $items
     *
     * @throws InvalidArgumentException a string is not UTF-8, or a value cannot be written as JSON
     */
    public static function listBody(#[\SensitiveParameter] array $items): string
    {
        return self::encode(\array_map(
            static fn(#[\SensitiveParameter] array $item): \stdClass => self::object($item),
            \array_values($items),
        ));
    }

    /**
     * A path with each `{}` filled by the next id, encoded. An empty id,
     * `.` or `..` is refused: the URL would lose or merge a segment of the
     * path and reach another route.
     *
     * @throws InvalidArgumentException an id is empty, `.` or `..`
     */
    public static function path(string $pattern, string ...$ids): string
    {
        $path = '';
        foreach (\explode('{}', $pattern) as $index => $part) {
            $path .= $part;
            if (isset($ids[$index])) {
                $path .= self::id($ids[$index]);
            }
        }

        return $path;
    }

    /**
     * The JSON exception is not kept as the previous one: its trace holds
     * the body, a message's content included.
     *
     * @param list<\stdClass>|\stdClass $body
     */
    private static function encode(#[\SensitiveParameter] array|\stdClass $body): string
    {
        try {
            return \json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException($error->getCode() === JSON_ERROR_UTF8
                ? 'A value to send is not valid UTF-8, which JSON cannot carry. Convert it to UTF-8 before the call.'
                : "A value to send cannot be written as JSON: {$error->getMessage()}.");
        }
    }

    private static function id(string $id): string
    {
        if (\in_array($id, ['', '.', '..'], true)) {
            throw new InvalidArgumentException(
                'An id in the path is empty, "." or "..", which would send the request to another route.',
            );
        }

        return \rawurlencode($id);
    }

    private static function value(int|string $key, #[\SensitiveParameter] mixed $value): mixed
    {
        if (\in_array($key, self::VERBATIM, true) && \is_array($value)) {
            return self::map($value);
        }
        if ($value instanceof Attachment) {
            return self::object($value->toWire());
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.v\Z');
        }
        if (\is_array($value)) {
            return self::isList($value)
                ? \array_map(static fn(#[\SensitiveParameter] mixed $item): mixed => self::value(0, $item), \array_values($value))
                : self::object($value);
        }

        return $value;
    }

    /** @param array<array-key, mixed> $value */
    private static function isList(#[\SensitiveParameter] array $value): bool
    {
        foreach (\array_keys($value) as $key) {
            if (!\is_int($key)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<array-key, mixed> $members */
    private static function object(#[\SensitiveParameter] array $members): \stdClass
    {
        $object = new \stdClass();
        foreach ($members as $name => $member) {
            if ($member !== null) {
                $object->{$name} = self::value($name, $member);
            }
        }

        return $object;
    }

    /** @param array<array-key, mixed> $map */
    private static function map(#[\SensitiveParameter] array $map): \stdClass
    {
        $object = new \stdClass();
        foreach ($map as $name => $member) {
            $object->{$name} = $member;
        }

        return $object;
    }
}
