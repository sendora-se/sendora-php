<?php

declare(strict_types=1);

namespace Sendora\Types;

/** Where an erasure stands: `pending` until Sendora has erased the person, `done` after. */
enum ErasureStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
}
