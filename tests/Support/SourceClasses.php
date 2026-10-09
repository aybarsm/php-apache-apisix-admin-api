<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

/**
 * Enumerates every class-like declared under src/.
 */
final class SourceClasses
{
    public const string NAMESPACE = 'Aybarsm\\Apache\\Apisix\\AdminApi\\';

    /**
     * @return list<ReflectionClass<object>>
     */
    public static function all(): array
    {
        $root = dirname(__DIR__, 2).'/src';
        $out = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $class = self::NAMESPACE.str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -4));
            if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                $out[] = new ReflectionClass($class);
            }
        }
        usort($out, static fn (ReflectionClass $a, ReflectionClass $b): int => $a->getName() <=> $b->getName());

        return $out;
    }

    /**
     * @return list<ReflectionClass<object>>
     */
    public static function inNamespace(string $relative): array
    {
        $prefix = self::NAMESPACE.trim($relative, '\\').'\\';

        return array_values(array_filter(self::all(), static fn (ReflectionClass $c): bool => str_starts_with($c->getName(), $prefix)));
    }
}
