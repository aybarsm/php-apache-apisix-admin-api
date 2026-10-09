<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Args;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\AttributeScanner;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecDiff;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecException;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;
use Override;

final readonly class DiffCommand implements Command
{
    public function __construct(
        private SpecLoader $loader,
        private AttributeScanner $scanner,
        private string $dtoDir,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'diff';
    }

    #[Override]
    public function usage(): string
    {
        return 'diff <from> [<to>] [--json]';
    }

    #[Override]
    public function description(): string
    {
        return 'Upgrade report between two pristine spec versions (to defaults to the latest).';
    }

    #[Override]
    public function run(Args $args, Output $output): int
    {
        $from = $args->argument(0) ?? throw new SpecException('Usage: '.$this->usage());
        $to = $args->argument(1) ?? $this->loader->latest();

        $diff = SpecDiff::compare(
            $this->loader->load($from, applyOverrides: false),
            $this->loader->load($to, applyOverrides: false),
            $this->loader->load($from)->overrides,
            $this->scanner->scan(),
            $this->dtoDir,
        );

        if ($args->flag('json')) {
            $output->line(json_encode(get_object_vars($diff), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $output->line(sprintf('# APISIX Admin API spec diff: v%s -> v%s', $diff->from, $diff->to));
        if ($diff->isEmpty()) {
            $output->line();
            $output->line('No structural changes.');

            return self::SUCCESS;
        }

        self::list($output, 'Operations added (implement or exclude)', $diff->addedOperations);
        self::list($output, 'Operations removed', $diff->removedOperations);
        self::nested($output, 'Operations changed', $diff->changedOperations);
        self::list($output, 'Schemas added', $diff->addedSchemas);
        self::list($output, 'Schemas removed', $diff->removedSchemas);
        self::nested($output, 'Schemas changed', $diff->changedSchemas);
        self::list($output, 'Impact: mapped methods whose operation was removed', $diff->brokenMappings);
        self::list($output, 'Impact: DTOs to regenerate or review', $diff->affectedDtos);
        self::list($output, 'Impact: overrides to re-check', array_map(
            static fn (string $key, string $reason): string => $key.' — '.$reason,
            array_keys($diff->overridesToRecheck),
            array_values($diff->overridesToRecheck),
        ));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $items
     */
    private static function list(Output $output, string $title, array $items): void
    {
        if ($items === []) {
            return;
        }
        $output->line();
        $output->line(sprintf('## %s (%d)', $title, count($items)));
        $output->line();
        foreach ($items as $item) {
            $output->line('- '.$item);
        }
    }

    /**
     * @param array<string, list<string>> $groups
     */
    private static function nested(Output $output, string $title, array $groups): void
    {
        if ($groups === []) {
            return;
        }
        $output->line();
        $output->line(sprintf('## %s (%d)', $title, count($groups)));
        foreach ($groups as $name => $changes) {
            $output->line();
            $output->line(sprintf('- `%s`', $name));
            foreach ($changes as $change) {
                $output->line('  - '.$change);
            }
        }
    }
}
