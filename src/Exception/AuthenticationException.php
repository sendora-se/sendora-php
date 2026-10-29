<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 401: the key is missing, malformed, unknown or revoked. */
final class AuthenticationException extends ApiStatusException {}
