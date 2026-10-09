<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `Upstream.type` values.
 */
enum UpstreamType: string
{
    case Roundrobin = 'roundrobin';
    case Chash = 'chash';
    case Ewma = 'ewma';
    case LeastConn = 'least_conn';
}
