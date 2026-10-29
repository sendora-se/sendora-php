<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 500 or more: a failure on the API's side. */
final class InternalServerException extends ApiStatusException {}
