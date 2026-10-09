<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Args;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\AttributeScanner;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\CoverageReport;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Mapping;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;
use Override;

final readonly class CoverageCommand implements Command
{
    public function __construct(
        private SpecLoader $loader,
        private AttributeScanner $scanner,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'coverage';
    }

    #[Override]
    public function usage(): string
    {
        return 'coverage [--spec=3.19.0]';
    }

    #[Override]
    public function description(): string
    {
        return 'Fail unless every non-excluded operation is mapped exactly once via #[SpecOperation].';
    }

    #[Override]
    public function run(Args $args, Output $output): int
    {
        $spec = $this->loader->load($args->option('spec'));
        $report = CoverageReport::build($spec, $this->scanner->scan());

        $implementable = $report->total - $report->excluded;
        $output->line(sprintf(
            'v%s: %d/%d implementable operations mapped (%d excluded).',
            $spec->version,
            count($report->mapped),
            $implementable,
            $report->excluded,
        ));

        foreach ($report->unmapped as $id) {
            $op = $spec->operations[$id];
            $output->line(sprintf('  UNMAPPED   %-38s %-6s %s', $id, $op->method, $op->path));
        }
        foreach ($report->excludedMapped as $id => $list) {
            $output->line(sprintf('  EXCLUDED   %-38s mapped by %s', $id, self::targets($list)));
        }
        foreach ($report->unknown as $mapping) {
            $output->line(sprintf('  UNKNOWN    %-38s mapped by %s', $mapping->operationId, $mapping->target()));
        }
        foreach ($report->duplicates as $id => $list) {
            $output->line(sprintf('  DUPLICATE  %-38s mapped by %s', $id, self::targets($list)));
        }

        if ($report->isComplete()) {
            $output->line('Coverage complete.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @param list<Mapping> $mappings
     */
    private static function targets(array $mappings): string
    {
        return implode(', ', array_map(static fn (Mapping $m): string => $m->target(), $mappings));
    }
}
