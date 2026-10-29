<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * What a stream carries: `transactional` for mail the recipient expects,
 * `broadcast` for mail to many, `inbound` for mail the server receives.
 */
enum StreamKind: string
{
    case Transactional = 'transactional';
    case Broadcast = 'broadcast';
    case Inbound = 'inbound';
}
