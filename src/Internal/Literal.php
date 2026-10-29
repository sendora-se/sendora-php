<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * A value that `Wire::body()` writes as JSON's `null`, where a plain `null`
 * means an argument left out.
 *
 * @internal
 */
enum Literal
{
    case Null;
}
