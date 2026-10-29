<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * Waits between attempts. The tests replace it, so that they never sleep.
 *
 * @internal
 */
interface Clock
{
    public function sleep(float $seconds): void;
}
