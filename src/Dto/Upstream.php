<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamHashOn;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamPassHost;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamScheme;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamType;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `Upstream`: Upstream configuration defining backend service nodes and load balancing behavior.
 */
final readonly class Upstream implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'Upstream';

    public const array KEYS = ['id', 'name', 'desc', 'labels', 'create_time', 'update_time', 'nodes', 'warm_up_conf', 'retries', 'retry_timeout', 'timeout', 'type', 'hash_on', 'key', 'scheme', 'checks', 'tls', 'keepalive_pool', 'pass_host', 'upstream_host', 'discovery_type', 'discovery_args', 'service_name'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'discovery_args' => 1];

    /**
     * @param array<string, string>|null $labels
     * @param list<UpstreamNodeItem>|array<string, int>|null $nodes node objects, or the `"host:port" => weight` shorthand
     * @param array<string, mixed>|null $discoveryArgs
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string|int|null $id = null,
        public ?string $name = null,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public ?array $nodes = null,
        public ?UpstreamWarmUp $warmUpConf = null,
        public ?int $retries = null,
        public int|float|null $retryTimeout = null,
        public ?Timeout $timeout = null,
        public ?UpstreamType $type = null,
        public ?UpstreamHashOn $hashOn = null,
        public ?string $key = null,
        public ?UpstreamScheme $scheme = null,
        public ?HealthCheck $checks = null,
        public ?UpstreamTls $tls = null,
        public ?KeepalivePool $keepalivePool = null,
        public ?UpstreamPassHost $passHost = null,
        public ?string $upstreamHost = null,
        public ?string $discoveryType = null,
        public ?array $discoveryArgs = null,
        public ?string $serviceName = null,
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
            nodes: self::nodes($data),
            warmUpConf: $data->dto('warm_up_conf', UpstreamWarmUp::class),
            retries: $data->int('retries'),
            retryTimeout: $data->number('retry_timeout'),
            timeout: $data->dto('timeout', Timeout::class),
            type: $data->enum('type', UpstreamType::class),
            hashOn: $data->enum('hash_on', UpstreamHashOn::class),
            key: $data->string('key'),
            scheme: $data->enum('scheme', UpstreamScheme::class),
            checks: $data->dto('checks', HealthCheck::class),
            tls: $data->dto('tls', UpstreamTls::class),
            keepalivePool: $data->dto('keepalive_pool', KeepalivePool::class),
            passHost: $data->enum('pass_host', UpstreamPassHost::class),
            upstreamHost: $data->string('upstream_host'),
            discoveryType: $data->string('discovery_type'),
            discoveryArgs: $data->map('discovery_args'),
            serviceName: $data->string('service_name'),
            extra: $data->extra(self::KEYS),
        );
    }

    /**
     * Spec `UpstreamNodes`: anyOf a weight map (`{"host:port": weight}`) or a list of node objects.
     *
     * @return list<UpstreamNodeItem>|array<string, int>|null
     */
    private static function nodes(Data $data): ?array
    {
        $raw = $data->mixed('nodes');

        return is_array($raw) && array_is_list($raw)
            ? $data->dtoList('nodes', UpstreamNodeItem::class)
            : $data->intMap('nodes');
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
            'nodes' => $this->nodes,
            'warm_up_conf' => $this->warmUpConf,
            'retries' => $this->retries,
            'retry_timeout' => $this->retryTimeout,
            'timeout' => $this->timeout,
            'type' => $this->type,
            'hash_on' => $this->hashOn,
            'key' => $this->key,
            'scheme' => $this->scheme,
            'checks' => $this->checks,
            'tls' => $this->tls,
            'keepalive_pool' => $this->keepalivePool,
            'pass_host' => $this->passHost,
            'upstream_host' => $this->upstreamHost,
            'discovery_type' => $this->discoveryType,
            'discovery_args' => $this->discoveryArgs,
            'service_name' => $this->serviceName,
        ];
    }
}
