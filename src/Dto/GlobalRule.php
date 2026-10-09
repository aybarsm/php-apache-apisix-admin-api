<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `GlobalRule`: Global Rule configuration. Global plugins apply independently of locally bound plugins. For HTTP requests, execution is interleaved by phase: global `rewrite`, local `rewrite`, global `access`, then local `access`. Priorities order plugins within each phase and scope, and an early response skips handlers scheduled later.
 */
final readonly class GlobalRule implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'GlobalRule';

    public const array KEYS = ['id', 'create_time', 'update_time', 'plugins'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['plugins' => 2];

    /**
     * @param array<string, array<string, mixed>> $plugins
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public array $plugins,
        public string|int|null $id = null,
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
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
            'plugins' => $this->plugins,
        ];
    }
}
