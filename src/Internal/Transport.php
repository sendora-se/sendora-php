<?php

declare(strict_types=1);

namespace Sendora\Internal;

use Sendora\Exception\ApiConnectionException;

/**
 * Speaks HTTP: one attempt at a call, and what came back.
 *
 * @internal
 */
interface Transport
{
    /**
     * @throws ApiConnectionException when no answer came
     */
    public function send(#[\SensitiveParameter] Call $call): Reply;
}
