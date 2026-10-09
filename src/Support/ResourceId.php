<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Support;

use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;

/**
 * Client-side validation of identifiers before they reach a URL.
 */
final readonly class ResourceId
{
    /** Spec `ResourceId` (string form): 1–64 chars of `[a-zA-Z0-9-_.]`. */
    public const string PATTERN = '/^[a-zA-Z0-9\-_.]{1,64}$/';

    /** Spec `UsernameInPath`: 1–256 chars of `[a-zA-Z0-9_-]`. */
    public const string USERNAME_PATTERN = '/^[a-zA-Z0-9_\-]{1,256}$/';

    /**
     * Validates a ResourceId (string or positive integer) and returns its path form.
     */
    public static function assert(string|int $id, string $name = 'id'): string
    {
        if (is_int($id)) {
            if ($id < 1) {
                throw new InvalidArgumentException(sprintf('%s must be a positive integer, got %d.', $name, $id));
            }

            return (string) $id;
        }

        if (preg_match(self::PATTERN, $id) !== 1) {
            throw new InvalidArgumentException(sprintf('%s "%s" must be 1-64 characters of [a-zA-Z0-9-_.].', $name, $id));
        }

        return $id;
    }

    public static function assertUsername(string $username): string
    {
        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            throw new InvalidArgumentException(sprintf('username "%s" must be 1-256 characters of [a-zA-Z0-9_-].', $username));
        }

        return $username;
    }

    /**
     * Validates a non-empty name used as a path segment (plugin names, etc.).
     */
    public static function assertName(string $value, string $name): string
    {
        if (trim($value) === '' || str_contains($value, '/')) {
            throw new InvalidArgumentException(sprintf('%s must be a non-empty single path segment.', $name));
        }

        return $value;
    }
}
