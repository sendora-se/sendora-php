<?php

declare(strict_types=1);

namespace Sendora\Internal;

/**
 * Waits for real, with `usleep`.
 *
 * @internal
 */
final class SystemClock implements Clock
{
    public function sleep(float $seconds): void
    {
        \usleep((int) \round($seconds * 1_000_000));
    }
}
