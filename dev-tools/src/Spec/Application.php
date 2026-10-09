<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands\Command;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands\CoverageCommand;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands\OpsCommand;
use Throwable;

/**
 * Entry point for `bin/spec`.
 */
final class Application
{
    /** @var array<string, Command> */
    private array $commands = [];

    public function __construct(string $root)
    {
        $loader = SpecLoader::forProject($root);
        $scanner = AttributeScanner::forProject($root);

        foreach ([
            new OpsCommand($loader, $scanner),
            new CoverageCommand($loader, $scanner),
        ] as $command) {
            $this->commands[$command->name()] = $command;
        }
    }

    /**
     * @param list<string> $argv arguments without the script name
     */
    public function run(array $argv, ?Output $output = null): int
    {
        $output ??= Output::stdout();
        $name = $argv[0] ?? 'help';
        $command = $this->commands[$name] ?? null;

        if ($command === null) {
            $this->help($output);

            return in_array($name, ['help', '--help', '-h'], true) ? Command::SUCCESS : Command::FAILURE;
        }

        try {
            return $command->run(Args::parse(array_slice($argv, 1)), $output);
        } catch (Throwable $e) {
            $output->line(sprintf('error: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function help(Output $output): void
    {
        $output->line('Usage: bin/spec <command> [options]');
        $output->line();
        foreach ($this->commands as $command) {
            $output->line(sprintf('  %-50s %s', $command->usage(), $command->description()));
        }
    }
}
