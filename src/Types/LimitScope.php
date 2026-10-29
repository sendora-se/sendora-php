<?php

declare(strict_types=1);

namespace Sendora\Types;

/**
 * Whose limit or cap it is: the account's, the server's own, or the test
 * cap that the account's test servers share.
 */
enum LimitScope: string
{
    case Account = 'account';
    case Server = 'server';
    case Test = 'test';
}
