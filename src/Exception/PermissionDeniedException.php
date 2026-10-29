<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 403: the key may not do this, or the account may not send. */
final class PermissionDeniedException extends ApiStatusException {}
