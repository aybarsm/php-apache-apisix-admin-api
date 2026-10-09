<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `PluginConfig`: Plugin Config — a reusable set of plugin configurations that can be referenced by multiple routes.
 */
final readonly class PluginConfig implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'PluginConfig';

    public const array KEYS = ['id', 'name', 'desc', 'labels', 'create_time', 'update_time', 'plugins'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'plugins' => 2];

    /**
     * @param array<string, array<string, mixed>> $plugins
     * @param array<string, string>|null $labels
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public array $plugins,
        public string|int|null $id = null,
        public ?string $name = null,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            plugins: $data->required($data->pluginMap('plugins'), 'plugins'),
            id: $data->id('id'),
            name: $data->string('name'),
            desc: $data->string('desc'),
            labels: $data->stringMap('labels'),
            createTime: $data->int('create_time'),
            updateTime: $data->int('update_time'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'desc' => $this->desc,
            'labels' => $this->labels,
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
            'plugins' => $this->plugins,
        ];
    }
}
