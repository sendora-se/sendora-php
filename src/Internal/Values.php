<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * Names the backed enum that lists the values a string property can hold
 * in this release. The property stays a string, so a value newer than the
 * release still comes through.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Values
{
    /** @param class-string<\BackedEnum> $enum */
    public function __construct(
        public readonly string $enum,
    ) {}
}
