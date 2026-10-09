<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * One entry of `listPluginAttributes` (inline schema `plugin_name`): a plugin's
 * version, priority and JSON schemas. Additional attributes APISIX returns
 * (`type`, `scope`, `metadata_schema`, `consumer_schema`) are kept in `$extra`.
 */
final readonly class PluginAttributes implements Dto
{
    use DtoBehaviour;

    public const array KEYS = ['version', 'schema', 'priority'];

    public const array OBJECT_KEYS = ['schema' => 1];

    /**
     * @param array<string, mixed>|null $schema plugin configuration JSON Schema
     * @param array<string, mixed>      $extra
     */
    public function __construct(
        public int|float|null $version = null,
        public ?array $schema = null,
        public ?int $priority = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            version: $data->number('version'),
            schema: $data->map('schema'),
            priority: $data->int('priority'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'version' => $this->version,
            'schema' => $this->schema,
            'priority' => $this->priority,
        ];
    }
}
