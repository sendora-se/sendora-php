<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What a limit counts over: the UTC minute, or the calendar month in UTC. */
enum LimitPeriod: string
{
    case Minute = 'minute';
    case Month = 'month';
}
