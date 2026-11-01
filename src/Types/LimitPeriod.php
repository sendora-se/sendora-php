<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What a limit counts over: the UTC minute, or the month, which runs from the day and time of the first payment for a paying account's live server and is otherwise the calendar month in UTC. */
enum LimitPeriod: string
{
    case Minute = 'minute';
    case Month = 'month';
}
