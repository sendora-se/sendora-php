<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\SendoraException;
use Sendora\Types\ErrorCode;

/**
 * Whether a failed attempt is repeated, and how long to wait first: the
 * failure must be retryable, the call idempotent or refused before it ran,
 * and the waiting within five seconds in all.
 *
 * @internal
 */
final class Retry
{
    /** The most a call waits across its retries; a longer `Retry-After` goes to the caller. */
    public const MAX_TOTAL_WAIT = 5.0;

    /**
     * Seconds to wait before another attempt, or `null` to give up.
     *
     * @param int $attempt the attempt's number, from 0
     * @param float $waited seconds already waited across the call
     * @param float $random a number in [0, 1) for the jitter
     */
    public static function delay(
        SendoraException $error,
        int $attempt,
        float $waited,
        int $maxRetries,
        bool $idempotent,
        float $random,
    ): ?float {
        if ($attempt >= $maxRetries || !$error->isRetryable()) {
            return null;
        }
        $refusedBeforeItRan = \in_array(
            $error->errorCode,
            [ErrorCode::RateLimited->value, ErrorCode::SendingDisabled->value],
            true,
        );
        if (!$idempotent && !$refusedBeforeItRan) {
            return null;
        }
        $delay = $error->retryAfter !== null
            ? $error->retryAfter
            : \round(\min(0.5 * 2 ** $attempt, 2.0) * (0.5 + $random), 3);

        return $waited + $delay > self::MAX_TOTAL_WAIT ? null : $delay;
    }
}
