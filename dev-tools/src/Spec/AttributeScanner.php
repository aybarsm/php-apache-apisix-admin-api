<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Mapping;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

/**
 * Discovers `#[SpecOperation]` mappings on public methods of PSR-4 classes.
 */
final readonly class AttributeScanner
{
    public const string NAMESPACE = 'Aybarsm\\Apache\\Apisix\\AdminApi\\';

    public function __construct(
        private string $sourceDir,
        private string $namespace = self::NAMESPACE,
    ) {}

    public static function forProject(string $root): self
    {
        return new self(rtrim($root, '/').'/src');
    }

    /**
     * @return list<Mapping>
     */
    public function scan(): array
    {
        $mappings = [];
        foreach ($this->classes() as $class) {
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                    continue;
                }
                foreach ($method->getAttributes(SpecOperation::class) as $attribute) {
                    $mappings[] = new Mapping($attribute->newInstance()->operationId, $class, $method->getName());
                }
            }
        }

        usort($mappings, static fn (Mapping $a, Mapping $b): int => [$a->operationId, $a->class, $a->method] <=> [$b->operationId, $b->class, $b->method]);

        return $mappings;
    }

    /**
     * @return list<class-string>
     */
    private function classes(): array
    {
        if (! is_dir($this->sourceDir)) {
            return [];
        }

        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->sourceDir, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen(rtrim($this->sourceDir, '/')) + 1, -4);
            $class = $this->namespace.str_replace('/', '\\', $relative);
            if (class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }
}
