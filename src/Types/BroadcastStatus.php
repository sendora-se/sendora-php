<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Where a broadcast stands: `accepted` until the first message leaves,
 * `processing` while messages leave, `completed` when every message has
 * left or failed, `cancelled` once cancelled.
 */
enum BroadcastStatus: string
{
    case Accepted = 'accepted';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
