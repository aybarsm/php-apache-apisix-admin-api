<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `SSL.type` values.
 */
enum SslType: string
{
    case Server = 'server';
    case Client = 'client';
}
