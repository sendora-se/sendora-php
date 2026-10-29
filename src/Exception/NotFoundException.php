<?php

declare(strict_types=1);

namespace Sendora\Exception;

/** 404: no such id on this server or account. */
final class NotFoundException extends ApiStatusException {}
