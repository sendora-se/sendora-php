<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** No answer came in time (`timeout`). */
final class ApiTimeoutException extends ApiConnectionException {}
