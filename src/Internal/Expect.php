<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * What a call answers when it succeeds: a JSON object, the bytes of a
 * download, or nothing.
 *
 * @internal
 */
enum Expect
{
    case Json;
    case Bytes;
    case Nothing;
}
