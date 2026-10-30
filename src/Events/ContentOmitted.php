<?php

declare(strict_types=1);

namespace Sendora\Events;

/** Why an `inbound` event left out the message's content: `too_large` for the delivery, or `expired`. */
enum ContentOmitted: string
{
    case TooLarge = 'too_large';
    case Expired = 'expired';
}
