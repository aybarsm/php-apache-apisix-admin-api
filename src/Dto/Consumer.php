<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `Consumer`: Consumer configuration. Represents an API user or application with authentication credentials and plugin policies.
 */
final readonly class Consumer implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'Consumer';

    public const array KEYS = ['username', 'desc', 'labels', 'create_time', 'update_time', 'group_id', 'plugins'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'plugins' => 2];

    /**
     * @param array<string, string>|null $labels
     * @param array<string, array<string, mixed>>|null $plugins
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $username,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public string|int|null $groupId = null,
        public ?array $plugins = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            username: $data->required($data->string('username'), 'username'),
            desc: $data->string('desc'),
            labels: $data->stringMap('labels'),
            createTime: $data->int('create_time'),
            updateTime: $data->int('update_time'),
            groupId: $data->id('group_id'),
            plugins: $data->pluginMap('plugins'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'username' => $this->username,
            'desc' => $this->desc,
            'labels' => $this->labels,
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
            'group_id' => $this->groupId,
            'plugins' => $this->plugins,
        ];
    }
}
