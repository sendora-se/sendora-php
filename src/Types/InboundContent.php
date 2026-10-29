<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * How the `inbound` event carries a received message: `full` sends the
 * parsed message with the event, and `reference` its ids and sizes only.
 */
enum InboundContent: string
{
    case Full = 'full';
    case Reference = 'reference';
}
