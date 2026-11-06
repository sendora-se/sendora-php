<?php

declare(strict_types=1);

namespace Sendora\Types;

/** The mail program an open or a click came from. */
enum TrackingClient: string
{
    case AppleMail = 'apple_mail';
    case Gmail = 'gmail';
    case Outlook = 'outlook';
    case Yahoo = 'yahoo';
    case Thunderbird = 'thunderbird';
    case Proton = 'proton';
    case Browser = 'browser';
    case Other = 'other';
}
