<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Exceptions;

/**
 * The Admin API answered with an error status (>= 400).
 */
abstract class ApiException extends ApacheApisixApiException
{
    final public function __construct(
        public readonly int $status,
        public readonly string $errorMsg,
        public readonly ?string $description,
        public readonly string $method,
        public readonly string $uri,
        public readonly string $rawBody,
    ) {
        parent::__construct(sprintf(
            '%s %s: %d %s%s',
            $method,
            $uri,
            $status,
            $errorMsg,
            $description === null || $description === '' ? '' : ' ('.$description.')',
        ), $status);
    }
}
