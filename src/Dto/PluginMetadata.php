<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Plugin metadata: a free-form object whose shape is the plugin's `metadata_schema`
 * (the spec declares it as an untyped object, so there is no component schema).
 */
final readonly class PluginMetadata implements Dto
{
    use DtoBehaviour;

    /** APISIX injects the plugin name as `id`. */
    public const array READ_ONLY = ['id'];

    /**
     * @param array<string, mixed> $config metadata keys, e.g. `['log_format' => [...]]`
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public array $config = [],
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(config: $data->extra([]));
    }

    #[Override]
    protected function payload(): array
    {
        return $this->config;
    }
}
