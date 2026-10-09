<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Exceptions;

use Throwable;

/**
 * A successful response could not be decoded or has an unexpected shape.
 */
final class UnexpectedResponseException extends ApacheApisixApiException
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly int $status,
        public readonly string $rawBody,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('%s %s: %d %s', $method, $uri, $status, $reason), 0, $previous);
    }
}
