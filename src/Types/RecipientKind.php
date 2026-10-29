<?php

declare(strict_types=1);

namespace Sendora\Types;

/** Which of `to`, `cc` and `bcc` named a recipient. */
enum RecipientKind: string
{
    case To = 'to';
    case Cc = 'cc';
    case Bcc = 'bcc';
}
