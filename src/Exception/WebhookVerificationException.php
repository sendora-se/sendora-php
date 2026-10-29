<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** A webhook request whose signature or body does not hold. */
final class WebhookVerificationException extends SendoraException {}
