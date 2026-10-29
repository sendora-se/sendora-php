<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 422: well formed, but refused, such as a suppressed recipient. */
final class UnprocessableEntityException extends ApiStatusException {}
