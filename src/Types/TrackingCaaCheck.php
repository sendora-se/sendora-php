<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * What a check of the CAA records on a tracking domain and its parents
 * found. `DnsError` is a resolver failure and changes nothing.
 */
enum TrackingCaaCheck: string
{
    case Ok = 'ok';
    case NoLetsEncrypt = 'no_lets_encrypt';
    case OtherAccount = 'other_account';
    case NoHttp01 = 'no_http_01';
    case DnsError = 'dns_error';
}
