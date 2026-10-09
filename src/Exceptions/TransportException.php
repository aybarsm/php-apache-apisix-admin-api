<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Exceptions;

use Throwable;

/**
 * The HTTP client failed before a response was received.
 */
abstract class TransportException extends ApacheApisixApiException
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('%s %s: %s', $method, $uri, $message), 0, $previous);
    }
}
