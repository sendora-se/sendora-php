<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 400: the request is malformed or refused as it stands. */
final class BadRequestException extends ApiStatusException {}
