<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * What a lookup of a record found. `dns_error` is a resolver failure, such
 * as a DNSSEC problem or a timeout, not an absent record.
 */
enum RecordCheck: string
{
    case Ok = 'ok';
    case Missing = 'missing';
    case Mismatch = 'mismatch';
    case DnsError = 'dns_error';
}
