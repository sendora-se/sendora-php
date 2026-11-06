<?php

declare(strict_types=1);

namespace Sendora\Types;

/** The kind of device an open or a click came from. */
enum TrackingDevice: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
}
