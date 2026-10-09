<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

/**
 * Resolves local JSON pointers (`#/components/...`) inside an OpenAPI document.
 */
final class RefResolver
{
    private const int MAX_CHAIN = 32;

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        private readonly array $document,
    ) {}

    /**
     * Returns the node a local `$ref` points to.
     *
     * @return array<string, mixed>
     */
    public function pointer(string $ref): array
    {
        if (isset($this->cache[$ref])) {
            return $this->cache[$ref];
        }

        if (! str_starts_with($ref, '#/')) {
            throw new SpecException(sprintf('Only local refs are supported, got "%s"', $ref));
        }

        $node = $this->document;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                throw new SpecException(sprintf('Unresolvable ref "%s"', $ref));
            }
            $node = $node[$segment];
        }

        return $this->cache[$ref] = Json::map($node, $ref);
    }

    /**
     * Follows a chain of `$ref`s until a concrete node is reached.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    public function resolve(array $node): array
    {
        $seen = [];
        while (isset($node['$ref']) && is_string($node['$ref'])) {
            $ref = $node['$ref'];
            if (isset($seen[$ref]) || count($seen) >= self::MAX_CHAIN) {
                throw new SpecException(sprintf('Circular ref chain at "%s"', $ref));
            }
            $seen[$ref] = true;
            $node = $this->pointer($ref);
        }

        return $node;
    }

    /**
     * Recursively inlines every `$ref`. Cycles are cut and marked with `x-cycle`.
     *
     * @param array<array-key, mixed> $node
     * @param array<string, true>     $stack
     *
     * @return array<array-key, mixed>
     */
    public function inline(array $node, array $stack = []): array
    {
        if (isset($node['$ref']) && is_string($node['$ref'])) {
            $ref = $node['$ref'];
            if (isset($stack[$ref])) {
                return ['$ref' => $ref, 'x-cycle' => true];
            }

            return $this->inline($this->pointer($ref), $stack + [$ref => true]);
        }

        $out = [];
        foreach ($node as $key => $value) {
            $out[$key] = is_array($value) ? $this->inline($value, $stack) : $value;
        }

        return $out;
    }

    /**
     * Name of the component a `$ref` targets, e.g. `Route` for `#/components/schemas/Route`.
     */
    public static function refName(string $ref): string
    {
        $pos = strrpos($ref, '/');

        return $pos === false ? $ref : substr($ref, $pos + 1);
    }
}
