<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `Upstream.pass_host` values.
 */
enum UpstreamPassHost: string
{
    case Pass = 'pass';
    case Node = 'node';
    case Rewrite = 'rewrite';
}
