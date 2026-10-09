<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Exceptions;

/**
 * 401 Unauthorized — missing or invalid API key, or insufficient role.
 */
final class UnauthorizedException extends ApiException {}
