<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Overrides;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\RefResolver;

final readonly class Spec
{
    /**
     * @param array<string, mixed>     $document   OpenAPI document with `schemas` overrides applied
     * @param array<string, Operation> $operations keyed by canonical operationId
     */
    public function __construct(
        public string $version,
        public array $document,
        public Overrides $overrides,
        public RefResolver $refs,
        public array $operations,
    ) {}

    public function operation(string $id): ?Operation
    {
        return $this->operations[$id] ?? null;
    }

    /**
     * @return array<string, Operation>
     */
    public function implementable(): array
    {
        return array_filter($this->operations, static fn (Operation $op): bool => ! $op->isExcluded());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function schemas(): array
    {
        $components = $this->document['components'] ?? null;
        $schemas = is_array($components) ? ($components['schemas'] ?? []) : [];
        $out = [];
        if (is_array($schemas)) {
            foreach ($schemas as $name => $schema) {
                if (is_array($schema)) {
                    $out[(string) $name] = array_combine(array_map('strval', array_keys($schema)), $schema);
                }
            }
        }

        return $out;
    }
}
