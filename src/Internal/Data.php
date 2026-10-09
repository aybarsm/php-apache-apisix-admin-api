<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\HydrationException;
use BackedEnum;
use ValueError;

/**
 * Typed, path-aware accessors over a decoded JSON object, used by DTO `fromArray()`.
 *
 * Optional accessors return null when the key is absent or null; `required*`
 * variants throw. Every type mismatch throws {@see HydrationException} with
 * the JSON path of the offending value.
 *
 * @internal
 */
final readonly class Data
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        private array $data,
        private string $path,
    ) {}

    public static function of(mixed $value, string $path): self
    {
        return new self(self::assertMap($value, $path), $path);
    }

    /**
     * @return array<string, mixed>
     */
    public static function assertMap(mixed $value, string $path): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new HydrationException($path, sprintf('expected an object, got %s', get_debug_type($value)));
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function path(string $key): string
    {
        return $this->path.'.'.$key;
    }

    public function mixed(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw $this->mismatch($key, 'string', $value);
    }

    public function requiredString(string $key): string
    {
        return $this->string($key) ?? throw $this->missing($key);
    }

    public function int(string $key): ?int
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value && abs($value) <= PHP_INT_MAX) {
            return (int) $value;
        }

        throw $this->mismatch($key, 'integer', $value);
    }

    public function requiredInt(string $key): int
    {
        return $this->int($key) ?? throw $this->missing($key);
    }

    public function float(string $key): ?float
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }

        throw $this->mismatch($key, 'number', $value);
    }

    public function requiredFloat(string $key): float
    {
        return $this->float($key) ?? throw $this->missing($key);
    }

    /**
     * Integer when integral, float otherwise (APISIX emits both for "number" fields).
     */
    public function number(string $key): int|float|null
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        throw $this->mismatch($key, 'number', $value);
    }

    public function requiredNumber(string $key): int|float
    {
        return $this->number($key) ?? throw $this->missing($key);
    }

    public function bool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || is_bool($value)) {
            return $value;
        }

        throw $this->mismatch($key, 'boolean', $value);
    }

    /**
     * A ResourceId: non-empty string or positive integer.
     */
    public function id(string $key): string|int|null
    {
        $value = $this->data[$key] ?? null;
        if ($value === null || (is_string($value) && $value !== '') || (is_int($value) && $value > 0)) {
            return $value;
        }

        throw $this->mismatch($key, 'resource id', $value);
    }

    /**
     * @return list<mixed>|null
     */
    public function list(string $key): ?array
    {
        $value = $this->data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        throw $this->mismatch($key, 'array', $value);
    }

    /**
     * @return list<string>|null
     */
    public function stringList(string $key): ?array
    {
        $list = $this->list($key);
        if ($list === null) {
            return null;
        }

        $out = [];
        foreach ($list as $i => $item) {
            if (! is_string($item)) {
                throw new HydrationException(sprintf('%s[%d]', $this->path($key), $i), sprintf('expected string, got %s', get_debug_type($item)));
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return list<int>|null
     */
    public function intList(string $key): ?array
    {
        $list = $this->list($key);
        if ($list === null) {
            return null;
        }

        $out = [];
        foreach ($list as $i => $item) {
            if (! is_int($item)) {
                throw new HydrationException(sprintf('%s[%d]', $this->path($key), $i), sprintf('expected integer, got %s', get_debug_type($item)));
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * A JSON object with arbitrary values. An empty JSON array is accepted as an empty object.
     *
     * @return array<string, mixed>|null
     */
    public function map(string $key): ?array
    {
        $value = $this->data[$key] ?? null;

        return $value === null ? null : self::assertMap($value, $this->path($key));
    }

    /**
     * @return array<string, string>|null
     */
    public function stringMap(string $key): ?array
    {
        $map = $this->map($key);
        if ($map === null) {
            return null;
        }

        $out = [];
        foreach ($map as $name => $item) {
            if (! is_string($item)) {
                throw new HydrationException($this->path($key).'.'.$name, sprintf('expected string, got %s', get_debug_type($item)));
            }
            $out[$name] = $item;
        }

        return $out;
    }

    /**
     * A `plugins` object: plugin name => configuration object.
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function pluginMap(string $key): ?array
    {
        $map = $this->map($key);
        if ($map === null) {
            return null;
        }

        $out = [];
        foreach ($map as $name => $config) {
            $out[$name] = self::assertMap($config, $this->path($key).'.'.$name);
        }

        return $out;
    }

    /**
     * @template T of Dto
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function dto(string $key, string $class): ?Dto
    {
        $value = $this->data[$key] ?? null;

        return $value === null ? null : $class::fromData(self::of($value, $this->path($key)));
    }

    /**
     * @template T of Dto
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function requiredDto(string $key, string $class): Dto
    {
        return $this->dto($key, $class) ?? throw $this->missing($key);
    }

    /**
     * @template T of Dto
     *
     * @param class-string<T> $class
     *
     * @return list<T>|null
     */
    public function dtoList(string $key, string $class): ?array
    {
        $list = $this->list($key);
        if ($list === null) {
            return null;
        }

        $out = [];
        foreach ($list as $i => $item) {
            $out[] = $class::fromData(self::of($item, sprintf('%s[%d]', $this->path($key), $i)));
        }

        return $out;
    }

    /**
     * @template T of BackedEnum
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function enum(string $key, string $class): ?BackedEnum
    {
        $value = $this->data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_int($value) && ! is_string($value)) {
            throw $this->mismatch($key, $class, $value);
        }

        try {
            return $class::from($value);
        } catch (ValueError|\TypeError) {
            throw new HydrationException($this->path($key), sprintf('unsupported %s value %s', $class, json_encode($value)));
        }
    }

    /**
     * Keys not described by the DTO, preserved verbatim for lossless round-trips.
     *
     * @param list<string> $known
     *
     * @return array<string, mixed>
     */
    public function extra(array $known): array
    {
        return array_diff_key($this->data, array_flip($known));
    }

    private function mismatch(string $key, string $expected, mixed $value): HydrationException
    {
        return new HydrationException($this->path($key), sprintf('expected %s, got %s', $expected, get_debug_type($value)));
    }

    private function missing(string $key): HydrationException
    {
        return new HydrationException($this->path($key), 'required value is missing');
    }
}
