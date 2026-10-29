<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What a DKIM or DMARC check found; `unchecked` until the checks have run. */
enum AuthVerdict: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case None = 'none';
    case TempError = 'temperror';
    case PermError = 'permerror';
    case Unchecked = 'unchecked';
}
