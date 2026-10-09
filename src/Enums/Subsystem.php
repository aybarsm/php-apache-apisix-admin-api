<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec parameter `Subsystem` (plugin endpoints).
 */
enum Subsystem: string
{
    case Http = 'http';
    case Stream = 'stream';
}
