<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Where a recipient's delivery stands: `queued` until the receiver
 * answers, and `deferred` while it keeps saying to try later.
 */
enum RecipientStatus: string
{
    case Queued = 'queued';
    case Delivered = 'delivered';
    case Deferred = 'deferred';
    case Bounced = 'bounced';
    case Expired = 'expired';
}
