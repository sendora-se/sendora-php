<?php

declare(strict_types=1);

namespace Sendora;

/**
 * The default of an argument whose `null` means something, so a left-out
 * argument and `null` can be told apart.
 */
enum Omit
{
    case Omit;
}
