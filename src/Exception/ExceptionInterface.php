<?php

declare(strict_types=1);

namespace Sendora\Exception;

/**
 * Marks everything the SDK itself throws, so that one `catch` takes all of
 * it: a failed call's `SendoraException` and a bad argument's
 * `InvalidArgumentException`.
 */
interface ExceptionInterface extends \Throwable {}
