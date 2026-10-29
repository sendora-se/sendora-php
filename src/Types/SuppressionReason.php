<?php

declare(strict_types=1);

namespace Sendora\Types;

/** Why an address is on a stream's suppression list. */
enum SuppressionReason: string
{
    case HardBounce = 'hard_bounce';
    case SpamComplaint = 'spam_complaint';
    case Manual = 'manual';
    case Unsubscribe = 'unsubscribe';
}
