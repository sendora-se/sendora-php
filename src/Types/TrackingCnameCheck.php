<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * What a check of a tracking domain's CNAME found. `Mismatch` is a record
 * that points elsewhere, an A record included. `Proxied` is a proxy in
 * front of the name. `DnsError` is a resolver failure and changes nothing.
 */
enum TrackingCnameCheck: string
{
    case Ok = 'ok';
    case Missing = 'missing';
    case Mismatch = 'mismatch';
    case Proxied = 'proxied';
    case DnsError = 'dns_error';
}
