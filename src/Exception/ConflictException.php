<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 409: it exists already, or its state forbids the change. */
final class ConflictException extends ApiStatusException {}
