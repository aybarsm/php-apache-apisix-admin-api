<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `Route.status` / `SSL.status` values.
 */
enum Status: int
{
    case Enabled = 1;
    case Disabled = 0;
}
