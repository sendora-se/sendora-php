<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Where a tracking domain stands. `Pending`: the records have not been
 * right yet. `WaitingForCertificate`: the records are right. `Ready`: new
 * mail from the sending domain uses the host. `Failing`: a check found the
 * records wrong, and new mail keeps using a host that was ready until 24
 * hours after `failingSince`. `Stopped`: those 24 hours have passed.
 * `SendingDomainLost`: the sending domain is deleted or has lost its
 * verification.
 */
enum TrackingDomainState: string
{
    case Pending = 'pending';
    case WaitingForCertificate = 'waiting_for_certificate';
    case Ready = 'ready';
    case Failing = 'failing';
    case Stopped = 'stopped';
    case SendingDomainLost = 'sending_domain_lost';
}
