<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What an SPF check found; `unchecked` until the checks have run. */
enum SpfVerdict: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case SoftFail = 'softfail';
    case Neutral = 'neutral';
    case None = 'none';
    case TempError = 'temperror';
    case PermError = 'permerror';
    case Unchecked = 'unchecked';
}
