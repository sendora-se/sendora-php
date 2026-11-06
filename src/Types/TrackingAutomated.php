<?php

declare(strict_types=1);

namespace Sendora\Types;

/** Whether a machine rather than a person did it: `Unknown` where nobody can tell, as with Apple Mail. */
enum TrackingAutomated: string
{
    case Likely = 'likely';
    case Unlikely = 'unlikely';
    case Unknown = 'unknown';
}
