<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

/**
 * Parses `positional --flag --key=value` style arguments.
 */
final readonly class Args
{
    /**
     * @param list<string>               $positional
     * @param array<string, string|true> $options
     */
    public function __construct(
        public array $positional = [],
        public array $options = [],
    ) {}

    /**
     * @param list<string> $argv
     */
    public static function parse(array $argv): self
    {
        $positional = [];
        $options = [];
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $options[$parts[0]] = $parts[1] ?? true;
            } else {
                $positional[] = $arg;
            }
        }

        return new self($positional, $options);
    }

    public function option(string $name): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function flag(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function argument(int $index): ?string
    {
        return $this->positional[$index] ?? null;
    }
}
