<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Tests\Support;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Spec;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;
use RuntimeException;

/**
 * Pulls request/response examples straight out of the latest spec, so tests use
 * the source of truth as fixtures.
 */
final class SpecExamples
{
    private static ?Spec $spec = null;

    public static function spec(): Spec
    {
        return self::$spec ??= SpecLoader::forProject(dirname(__DIR__, 2))->load();
    }

    /**
     * Every example value of an operation's response (`examples.*.value` and `example`).
     *
     * @return array<string, mixed> example name => value
     */
    public static function response(string $operationId, string $status = '200'): array
    {
        $op = self::spec()->operation($operationId) ?? throw new RuntimeException("Unknown operation {$operationId}");
        $paths = self::spec()->document['paths'];
        $raw = $paths[$op->path][strtolower($op->method)]['responses'][$status] ?? throw new RuntimeException("{$operationId} has no {$status} response");
        $raw = self::spec()->refs->resolve($raw);

        return self::examples($raw['content']['application/json'] ?? []);
    }

    /**
     * Every example value of a component schema.
     *
     * @return list<mixed>
     */
    public static function schema(string $name): array
    {
        $schema = self::spec()->schemas()[$name] ?? throw new RuntimeException("Unknown schema {$name}");

        return array_values(is_array($schema['examples'] ?? null) ? $schema['examples'] : []);
    }

    /**
     * @param array<string, mixed> $media
     *
     * @return array<string, mixed>
     */
    private static function examples(array $media): array
    {
        $out = [];
        foreach ((array) ($media['examples'] ?? []) as $name => $example) {
            $example = self::spec()->refs->resolve((array) $example);
            if (array_key_exists('value', $example)) {
                $out[(string) $name] = $example['value'];
            }
        }
        if (array_key_exists('example', $media)) {
            $out['example'] = $media['example'];
        }

        return $out;
    }
}
