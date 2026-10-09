<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Tests\Support;

/**
 * Integration settings from the environment, falling back to a gitignored `.env`.
 */
final class Env
{
    public const string DEFAULT_ADMIN_URL = 'http://apache-apisix.internal:9180';

    /** @var array<string, string>|null */
    private static ?array $file = null;

    public static function get(string $name, ?string $default = null): ?string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return self::file()[$name] ?? $default;
    }

    public static function adminUrl(): string
    {
        return self::get('APISIX_ADMIN_URL') ?? self::DEFAULT_ADMIN_URL;
    }

    public static function adminKey(): ?string
    {
        return self::get('APISIX_ADMIN_KEY');
    }

    /**
     * @return array<string, string>
     */
    private static function file(): array
    {
        if (self::$file !== null) {
            return self::$file;
        }

        self::$file = [];
        $path = dirname(__DIR__, 2).'/.env';
        if (! is_file($path)) {
            return self::$file;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ($value !== '') {
                self::$file[trim($key)] = trim($value, "\"'");
            }
        }

        return self::$file;
    }
}
