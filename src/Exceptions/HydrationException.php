<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Exceptions;

use Throwable;

/**
 * Data could not be mapped onto a DTO; `$path` points at the offending value.
 */
final class HydrationException extends ApacheApisixApiException
{
    public function __construct(
        public readonly string $path,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('%s: %s', $path, $reason), 0, $previous);
    }
}
