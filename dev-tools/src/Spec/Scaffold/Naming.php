<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold;

/**
 * Spec name => PHP name conventions shared by the generators.
 */
final class Naming
{
    /**
     * `UpstreamTLS` => `UpstreamTls`, `SSLRead` => `SslRead`, `upstream_tls` => `UpstreamTls`.
     */
    public static function className(string $name): string
    {
        $name = str_contains($name, '_') || str_contains($name, '-') || str_contains($name, ' ')
            ? str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($name))))
            : ucfirst($name);

        return (string) preg_replace_callback(
            '/([A-Z])([A-Z]+)(?=[A-Z][a-z]|[0-9]|$)/',
            static fn (array $m): string => $m[1].strtolower($m[2]),
            $name,
        );
    }

    /**
     * `create_time` => `createTime`.
     */
    public static function property(string $key): string
    {
        return lcfirst(self::className($key));
    }

    /**
     * Enum case name for a backed value: `least_conn` => `LeastConn`.
     *
     * Integer values use the spec's `x-enumDescriptions` (`"Enabled (default)."` => `Enabled`)
     * when given, `Value1` otherwise.
     */
    public static function enumCase(string|int $value, ?string $description = null): string
    {
        if (is_int($value) || ctype_digit($value)) {
            $label = $description === null ? '' : trim((string) preg_replace('/[(.,:;].*$/s', '', $description));
            $label = (string) preg_replace('/[^A-Za-z0-9]+/', '_', $label);

            return $label === '' || $label === '_' ? 'Value'.$value : self::className(trim($label, '_'));
        }

        $case = self::className((string) preg_replace('/[^A-Za-z0-9]+/', '_', $value));

        return ctype_digit($case[0] ?? '') ? 'V'.$case : $case;
    }
}
