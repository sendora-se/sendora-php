<?php

declare(strict_types=1);

namespace Sendora\Types;

/** What the sender's domain asks a receiver to do with mail that fails DMARC. */
enum DmarcPolicy: string
{
    case None = 'none';
    case Quarantine = 'quarantine';
    case Reject = 'reject';
}
