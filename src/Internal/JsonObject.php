<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * One JSON object of an answer as it came, objects kept as objects, with
 * where it sits in the answer for the decoder's messages.
 *
 * @internal
 */
final class JsonObject
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly \stdClass $object,
        public readonly string $path = '',
    ) {}

    /**
     * The JSON object a text holds, or `null` when it holds anything else.
     * An integer too large for PHP stays a string, so that the decoder
     * refuses it rather than read a float.
     */
    public static function parse(#[\SensitiveParameter] string $text): ?self
    {
        try {
            $value = \json_decode($text, false, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return $value instanceof \stdClass ? new self($value) : null;
    }

    /**
     * The object as arrays, as `json_decode()` with `true` makes it: an
     * empty object is an empty array, and a decimal key is an integer.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return self::plain(\get_object_vars($this->object));
    }

    /** A deep copy of the object, so a caller's change never reaches it. */
    public function copy(): \stdClass
    {
        $copy = new \stdClass();
        foreach (\get_object_vars($this->object) as $key => $member) {
            $copy->{$key} = self::copied($member);
        }

        return $copy;
    }

    /**
     * @param array<array-key, mixed> $members
     *
     * @return array<array-key, mixed>
     */
    private static function plain(#[\SensitiveParameter] array $members): array
    {
        return \array_map(
            static fn(#[\SensitiveParameter] mixed $member): mixed => match (true) {
                $member instanceof \stdClass => self::plain(\get_object_vars($member)),
                \is_array($member) => self::plain($member),
                default => $member,
            },
            $members,
        );
    }

    private static function copied(#[\SensitiveParameter] mixed $member): mixed
    {
        if ($member instanceof \stdClass) {
            return (new self($member))->copy();
        }
        if (\is_array($member)) {
            return \array_map(self::copied(...), $member);
        }

        return $member;
    }
}
