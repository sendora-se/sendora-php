<?php

declare(strict_types=1);

namespace Sendora\Types;

/** The proxy or machine an open or a click came through. */
enum TrackingAgent: string
{
    case ApplePrivacy = 'apple_privacy';
    case GmailPrefetch = 'gmail_prefetch';
    case GmailProxy = 'gmail_proxy';
    case YahooProxy = 'yahoo_proxy';
    case OutlookPrefetch = 'outlook_prefetch';
    case ProtonProxy = 'proton_proxy';
    case Scanner = 'scanner';
    case Crawler = 'crawler';
}
