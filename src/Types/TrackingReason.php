<?php

declare(strict_types=1);

namespace Sendora\Types;

/** Why an open or a click was labelled a machine's. */
enum TrackingReason: string
{
    case PrivacyProxy = 'privacy_proxy';
    case PrefetchSignature = 'prefetch_signature';
    case DeliveryTiming = 'delivery_timing';
    case LinkBurst = 'link_burst';
    case ScannerNetwork = 'scanner_network';
    case BotUserAgent = 'bot_user_agent';
    case ManyNetworks = 'many_networks';
}
