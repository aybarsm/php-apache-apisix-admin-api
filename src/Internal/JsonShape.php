<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use BackedEnum;
use stdClass;

/**
 * Keeps JSON objects as objects: PHP cannot tell `{}` from `[]` once decoded,
 * but APISIX rejects `[]` where its schema expects an object.
 *
 * @internal
 */
final class JsonShape
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, int>   $objectKeys key => depth of nested maps that must remain objects
     *
     * @return array<string, mixed>
     */
    public static function apply(array $payload, array $objectKeys): array
    {
        foreach ($objectKeys as $key => $depth) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = self::objectify($payload[$key], $depth);
            }
        }

        return $payload;
    }

    /**
     * Recursively converts DTOs and enums to plain PHP values.
     */
    public static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof Dto => $value->toArray(),
            $value instanceof BackedEnum => $value->value,
            is_array($value) => array_map(self::plain(...), $value),
            default => $value,
        };
    }

    private static function objectify(mixed $value, int $depth): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return new stdClass();
        }
        if ($depth > 1 && ! array_is_list($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::objectify($item, $depth - 1);
            }
        }

        return $value;
    }
}
