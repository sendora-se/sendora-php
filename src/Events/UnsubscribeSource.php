<?php

declare(strict_types=1);

namespace Sendora\Events;

/** How a recipient unsubscribed: `link` by the page's button, `one_click` by a mail client's request. */
enum UnsubscribeSource: string
{
    case Link = 'link';
    case OneClick = 'one_click';
}
