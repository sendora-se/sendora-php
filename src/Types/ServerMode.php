<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Whether a server delivers: `live` does, and `test` goes through
 * everything but delivery, so nothing reaches a recipient. It is fixed when
 * the server is created.
 */
enum ServerMode: string
{
    case Live = 'live';
    case Test = 'test';
}
