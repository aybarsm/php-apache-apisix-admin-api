<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

/**
 * Minimal line-oriented console output that can also buffer for tests.
 */
final class Output
{
    private string $buffer = '';

    /**
     * @param resource|null $stream
     */
    private function __construct(
        private readonly mixed $stream,
    ) {}

    public static function stdout(): self
    {
        return new self(STDOUT);
    }

    public static function buffered(): self
    {
        return new self(null);
    }

    public function line(string $text = ''): void
    {
        $this->write($text.PHP_EOL);
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = array_map(strlen(...), $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen($cell));
            }
        }

        $format = static function (array $cells) use ($widths): string {
            $out = [];
            foreach ($widths as $i => $width) {
                $cell = $cells[$i] ?? '';
                $out[] = str_pad(is_string($cell) ? $cell : '', $width);
            }

            return rtrim(implode('  ', $out));
        };

        $this->line($format($headers));
        $this->line($format(array_map(static fn (int $w): string => str_repeat('-', $w), $widths)));
        foreach ($rows as $row) {
            $this->line($format($row));
        }
    }

    public function contents(): string
    {
        return $this->buffer;
    }

    private function write(string $text): void
    {
        if (is_resource($this->stream)) {
            fwrite($this->stream, $text);

            return;
        }

        $this->buffer .= $text;
    }
}
