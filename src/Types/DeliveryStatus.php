<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Where a delivery stands: `pending` waits for its next attempt,
 * `delivered` was taken by the endpoint, and `dead` gave up and can be
 * replayed.
 */
enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Dead = 'dead';
}
