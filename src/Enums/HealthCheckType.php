<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `HealthCheckActive.type` / `HealthCheckPassive.type` values.
 */
enum HealthCheckType: string
{
    case Http = 'http';
    case Https = 'https';
    case Tcp = 'tcp';
}
