<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Args;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\AttributeScanner;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Mapping;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;
use Override;

final readonly class OpsCommand implements Command
{
    public function __construct(
        private SpecLoader $loader,
        private AttributeScanner $scanner,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'ops';
    }

    #[Override]
    public function usage(): string
    {
        return 'ops [--spec=3.19.0] [--tag="Routes"] [--json]';
    }

    #[Override]
    public function description(): string
    {
        return 'List spec operations with their client mapping (or exclusion).';
    }

    #[Override]
    public function run(Args $args, Output $output): int
    {
        $spec = $this->loader->load($args->option('spec'));
        $tag = $args->option('tag');

        $targets = [];
        foreach ($this->scanner->scan() as $mapping) {
            $targets[$mapping->operationId][] = $mapping;
        }

        $rows = [];
        $json = [];
        foreach ($spec->operations as $id => $op) {
            if ($tag !== null && strcasecmp($op->tag(), $tag) !== 0) {
                continue;
            }
            $mapped = array_map(static fn (Mapping $m): string => $m->target(), $targets[$id] ?? []);
            $status = $op->isExcluded() ? 'EXCLUDED' : ($mapped === [] ? 'UNMAPPED' : implode(', ', $mapped));
            $rows[] = [$op->method, $op->path, $id, $op->tag(), $status];
            $json[] = ['id' => $id, 'specId' => $op->specId, 'method' => $op->method, 'path' => $op->path, 'tag' => $op->tag(), 'mapping' => $mapped, 'excluded' => $op->excluded];
        }

        if ($args->flag('json')) {
            $output->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        usort($rows, static fn (array $a, array $b): int => [$a[3], $a[1], $a[0]] <=> [$b[3], $b[1], $b[0]]);
        $output->table(['METHOD', 'PATH', 'OPERATION', 'TAG', 'MAPPING'], $rows);

        $excluded = count(array_filter($rows, static fn (array $r): bool => $r[4] === 'EXCLUDED'));
        $unmapped = count(array_filter($rows, static fn (array $r): bool => $r[4] === 'UNMAPPED'));
        $output->line();
        $output->line(sprintf(
            'v%s: %d operations, %d mapped, %d excluded, %d unmapped',
            $spec->version,
            count($rows),
            count($rows) - $excluded - $unmapped,
            $excluded,
            $unmapped,
        ));

        return self::SUCCESS;
    }
}
