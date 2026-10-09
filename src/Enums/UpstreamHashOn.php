<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `Upstream.hash_on` values.
 */
enum UpstreamHashOn: string
{
    case Vars = 'vars';
    case Header = 'header';
    case Cookie = 'cookie';
    case Consumer = 'consumer';
    case VarsCombinations = 'vars_combinations';
}
