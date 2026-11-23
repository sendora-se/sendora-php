<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What a limit counts over: the UTC minute, the calendar day in UTC, or the month. A paying account's live server counts its month from the day and time of the first payment, and any other server the calendar month in UTC. */
enum LimitPeriod: string
{
    case Minute = 'minute';
    case Day = 'day';
    case Month = 'month';
}
