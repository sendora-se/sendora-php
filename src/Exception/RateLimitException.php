<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 429: `rate_limited`, which the SDK retries within its budget, or `monthly_cap_reached`. */
final class RateLimitException extends ApiStatusException {}
