<?php

declare(strict_types=1);

namespace Sendora\Exception;

/**
 * An argument the SDK cannot send, such as a string that is not UTF-8,
 * refused before any request. It is no `SendoraException`, since no call
 * was made, and `catch (\InvalidArgumentException)` still catches it.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface {}
