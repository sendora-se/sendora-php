<?php

declare(strict_types=1);

namespace Sendora\Types;

/** Why tracking stayed out of at least one copy of a message. */
enum TrackingSkipped: string
{
    case NoTrackingDomain = 'no_tracking_domain';
    case GmailClipping = 'gmail_clipping';
}
