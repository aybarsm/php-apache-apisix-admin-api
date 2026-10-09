<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApiException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ConflictException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ForbiddenException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ServerException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnauthorizedException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnexpectedStatusException;

/**
 * Maps error responses (`{"error_msg": ..., "description": ...}`) onto the exception hierarchy.
 *
 * @internal
 */
final class ExceptionFactory
{
    private const int MAX_RAW_MESSAGE = 200;

    public static function fromResponse(Response $response): ApiException
    {
        $class = match (true) {
            $response->status === 400 => BadRequestException::class,
            $response->status === 401 => UnauthorizedException::class,
            $response->status === 403 => ForbiddenException::class,
            $response->status === 404 => NotFoundException::class,
            $response->status === 409 => ConflictException::class,
            $response->status >= 500 => ServerException::class,
            default => UnexpectedStatusException::class,
        };

        $body = is_array($response->body) ? $response->body : [];
        $errorMsg = is_string($body['error_msg'] ?? null) ? $body['error_msg'] : null;
        $description = is_string($body['description'] ?? null) ? $body['description'] : null;

        if ($errorMsg === null) {
            $raw = trim($response->raw);
            $errorMsg = $raw === '' ? 'empty response body' : mb_strimwidth($raw, 0, self::MAX_RAW_MESSAGE, '...');
        }

        return new $class($response->status, $errorMsg, $description, $response->method, $response->uri, $response->raw);
    }
}
