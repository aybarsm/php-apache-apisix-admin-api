<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `Service`: Service configuration. A service groups an upstream and plugins so multiple routes can share the same backend configuration.
 */
final readonly class Service implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'Service';

    public const array KEYS = ['id', 'name', 'desc', 'labels', 'create_time', 'update_time', 'plugins', 'upstream', 'upstream_id', 'script', 'enable_websocket', 'hosts'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'plugins' => 2];

    /**
     * @param array<string, string>|null $labels
     * @param array<string, array<string, mixed>>|null $plugins
     * @param list<string>|null $hosts
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string|int|null $id = null,
        public ?string $name = null,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public ?array $plugins = null,
        public ?Upstream $upstream = null,
        public string|int|null $upstreamId = null,
        public ?string $script = null,
        public ?bool $enableWebsocket = null,
        public ?array $hosts = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            id: $data->id('id'),
            name: $data->string('name'),
            desc: $data->string('desc'),
            labels: $data->stringMap('labels'),
            createTime: $data->int('create_time'),
            updateTime: $data->int('update_time'),
            plugins: $data->pluginMap('plugins'),
            upstream: $data->dto('upstream', Upstream::class),
            upstreamId: $data->id('upstream_id'),
            script: $data->string('script'),
            enableWebsocket: $data->bool('enable_websocket'),
            hosts: $data->stringList('hosts'),
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
            'upstream' => $this->upstream,
            'upstream_id' => $this->upstreamId,
            'script' => $this->script,
            'enable_websocket' => $this->enableWebsocket,
            'hosts' => $this->hosts,
        ];
    }
}
