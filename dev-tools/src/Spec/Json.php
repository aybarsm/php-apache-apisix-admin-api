<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use JsonException;

/**
 * Narrowing helpers for decoded JSON documents.
 */
final class Json
{
    /**
     * @return array<string, mixed>
     */
    public static function decodeFile(string $path): array
    {
        if (! is_file($path)) {
            throw new SpecException(sprintf('File not found: %s', $path));
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new SpecException(sprintf('Unable to read: %s', $path));
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SpecException(sprintf('Invalid JSON in %s: %s', $path, $e->getMessage()), 0, $e);
        }

        return self::map($decoded, basename($path));
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value, string $context): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new SpecException(sprintf('%s: expected an object', $context));
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function optionalMap(mixed $value, string $context): array
    {
        return $value === null ? [] : self::map($value, $context);
    }

    /**
     * @return list<mixed>
     */
    public static function list(mixed $value, string $context): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new SpecException(sprintf('%s: expected an array', $context));
        }

        return $value;
    }

    public static function string(mixed $value, string $context): string
    {
        if (! is_string($value)) {
            throw new SpecException(sprintf('%s: expected a string', $context));
        }

        return $value;
    }

    public static function nonEmptyString(mixed $value, string $context): string
    {
        $string = self::string($value, $context);
        if (trim($string) === '') {
            throw new SpecException(sprintf('%s: must not be empty', $context));
        }

        return $string;
    }

    public static function isMap(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }
}
