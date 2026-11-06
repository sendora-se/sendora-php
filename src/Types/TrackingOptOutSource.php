<?php

declare(strict_types=1);

namespace Sendora\Types;

/** How an address came onto a server's not-tracked list. */
enum TrackingOptOutSource: string
{
    case Manual = 'manual';
    case Recipient = 'recipient';
}
