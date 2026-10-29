<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** The API answered with an error. A status without a class of its own, such as 402, 410 or 413, is this class. */
class ApiStatusException extends SendoraException {}
