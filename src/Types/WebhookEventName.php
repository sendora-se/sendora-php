<?php

declare(strict_types=1);

namespace Sendora\Types;

/** The events that a webhook can receive. */
enum WebhookEventName: string
{
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Deferred = 'deferred';
    case SpamComplaint = 'spam_complaint';
    case Unsubscribed = 'unsubscribed';
    case CapWarning = 'cap_warning';
    case CapReached = 'cap_reached';
    case Inbound = 'inbound';
    case InboundCapWarning = 'inbound_cap_warning';
    case InboundCapReached = 'inbound_cap_reached';
}
