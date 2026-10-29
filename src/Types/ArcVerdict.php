<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What the ARC check found; `unchecked` until the checks have run. */
enum ArcVerdict: string
{
    case None = 'none';
    case Pass = 'pass';
    case Fail = 'fail';
    case Unchecked = 'unchecked';
}
