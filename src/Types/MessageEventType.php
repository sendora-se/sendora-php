<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * A step of a message's timeline: `Reception` when the mail server took
 * the message, `Delivery`, `TransientFailure`, `Bounce`, `OOB` for a
 * bounce report received by mail, and `Feedback` for a spam complaint.
 */
enum MessageEventType: string
{
    case Reception = 'Reception';
    case Delivery = 'Delivery';
    case Bounce = 'Bounce';
    case TransientFailure = 'TransientFailure';
    case Expiration = 'Expiration';
    case AdminBounce = 'AdminBounce';
    case Oob = 'OOB';
    case Feedback = 'Feedback';
    case AdminRebind = 'AdminRebind';
    case DeferredInjectionRebind = 'DeferredInjectionRebind';
    case Rejection = 'Rejection';
    case Delayed = 'Delayed';
}
