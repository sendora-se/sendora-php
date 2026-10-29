<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** No answer came: the connection failed (`connection_failed`), or the answer could not be read. */
class ApiConnectionException extends SendoraException {}
