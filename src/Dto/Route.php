<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Status;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `Route`: Route configuration. A route matches incoming requests by URI, host, methods, and other conditions, then forwards them to an upstream.
 */
final readonly class Route implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'Route';

    public const array KEYS = ['id', 'name', 'desc', 'labels', 'create_time', 'update_time', 'uri', 'uris', 'host', 'hosts', 'methods', 'remote_addr', 'remote_addrs', 'priority', 'vars', 'filter_func', 'plugins', 'plugin_config_id', 'upstream', 'upstream_id', 'service_id', 'timeout', 'enable_websocket', 'status', 'script', 'script_id'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'plugins' => 2];

    /**
     * @param array<string, string>|null $labels
     * @param list<string>|null $uris
     * @param list<string>|null $hosts
     * @param list<string>|null $methods
     * @param list<string>|null $remoteAddrs
     * @param list<mixed>|null $vars
     * @param array<string, array<string, mixed>>|null $plugins
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string|int|null $id = null,
        public ?string $name = null,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public ?string $uri = null,
        public ?array $uris = null,
        public ?string $host = null,
        public ?array $hosts = null,
        public ?array $methods = null,
        public ?string $remoteAddr = null,
        public ?array $remoteAddrs = null,
        public ?int $priority = null,
        public ?array $vars = null,
        public ?string $filterFunc = null,
        public ?array $plugins = null,
        public string|int|null $pluginConfigId = null,
        public ?Upstream $upstream = null,
        public string|int|null $upstreamId = null,
        public string|int|null $serviceId = null,
        public ?Timeout $timeout = null,
        public ?bool $enableWebsocket = null,
        public ?Status $status = null,
        public ?string $script = null,
        public string|int|null $scriptId = null,
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
            uri: $data->string('uri'),
            uris: $data->stringList('uris'),
            host: $data->string('host'),
            hosts: $data->stringList('hosts'),
            methods: $data->stringList('methods'),
            remoteAddr: $data->string('remote_addr'),
            remoteAddrs: $data->stringList('remote_addrs'),
            priority: $data->int('priority'),
            vars: $data->list('vars'),
            filterFunc: $data->string('filter_func'),
            plugins: $data->pluginMap('plugins'),
            pluginConfigId: $data->id('plugin_config_id'),
            upstream: $data->dto('upstream', Upstream::class),
            upstreamId: $data->id('upstream_id'),
            serviceId: $data->id('service_id'),
            timeout: $data->dto('timeout', Timeout::class),
            enableWebsocket: $data->bool('enable_websocket'),
            status: $data->enum('status', Status::class),
            script: $data->string('script'),
            scriptId: $data->id('script_id'),
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
            'uri' => $this->uri,
            'uris' => $this->uris,
            'host' => $this->host,
            'hosts' => $this->hosts,
            'methods' => $this->methods,
            'remote_addr' => $this->remoteAddr,
            'remote_addrs' => $this->remoteAddrs,
            'priority' => $this->priority,
            'vars' => $this->vars,
            'filter_func' => $this->filterFunc,
            'plugins' => $this->plugins,
            'plugin_config_id' => $this->pluginConfigId,
            'upstream' => $this->upstream,
            'upstream_id' => $this->upstreamId,
            'service_id' => $this->serviceId,
            'timeout' => $this->timeout,
            'enable_websocket' => $this->enableWebsocket,
            'status' => $this->status,
            'script' => $this->script,
            'script_id' => $this->scriptId,
        ];
    }
}
