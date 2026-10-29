<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Where a message stands: `accepted` until it is handed to the mail
 * server, `injected` after, and `failed` if that never worked.
 */
enum MessageStatus: string
{
    case Accepted = 'accepted';
    case Injected = 'injected';
    case Failed = 'failed';
}
