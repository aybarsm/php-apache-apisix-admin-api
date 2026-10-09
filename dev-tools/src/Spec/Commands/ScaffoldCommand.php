<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Args;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\DtoGenerator;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\Naming;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\ResourceGenerator;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecException;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;
use Override;

final readonly class ScaffoldCommand implements Command
{
    public function __construct(
        private SpecLoader $loader,
        private string $root,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'scaffold';
    }

    #[Override]
    public function usage(): string
    {
        return 'scaffold dto <Schema> [--deep] | resource <Tag> [--class=Name] [--spec=] [--write]';
    }

    #[Override]
    public function description(): string
    {
        return 'Generate DTO/enum or resource stubs from the spec (prints unless --write; never overwrites).';
    }

    #[Override]
    public function run(Args $args, Output $output): int
    {
        $spec = $this->loader->load($args->option('spec'));
        $kind = $args->argument(0);
        $subject = $args->argument(1) ?? throw new SpecException('Missing <Schema> or <Tag> argument.');

        $files = match ($kind) {
            'dto' => $this->dtoFiles(new DtoGenerator($spec), $subject, $args->flag('deep')),
            'resource' => (new ResourceGenerator($spec))->files($subject, $args->option('class')),
            default => throw new SpecException('Usage: '.$this->usage()),
        };

        foreach ($files as $path => $source) {
            if (! $args->flag('write')) {
                $output->line('// ==> '.$path);
                $output->line($source);
                continue;
            }

            $absolute = $this->root.'/'.$path;
            if (is_file($absolute)) {
                $output->line('skip   '.$path.' (exists)');
                continue;
            }
            if (! is_dir(dirname($absolute)) && ! mkdir(dirname($absolute), 0o755, true)) {
                throw new SpecException('Cannot create directory for '.$path);
            }
            file_put_contents($absolute, $source);
            $output->line('write  '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> relative path => source
     */
    private function dtoFiles(DtoGenerator $generator, string $schema, bool $deep): array
    {
        $files = [];
        foreach ($deep ? $generator->closure($schema) : [$schema] as $name) {
            $files['src/Dto/'.Naming::className($name).'.php'] = $generator->dtoSource($name);
            foreach ($generator->enumSources($name) as $enum => $source) {
                $files['src/Enums/'.$enum.'.php'] = $source;
            }
        }

        return $files;
    }
}
