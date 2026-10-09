<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `StreamRoute`: Stream Route for TCP/UDP (L4) proxying. Matches transport-layer connections by client IP, server address/port, or SNI.
 */
final readonly class StreamRoute implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'StreamRoute';

    public const array KEYS = ['id', 'name', 'desc', 'labels', 'create_time', 'update_time', 'remote_addr', 'server_addr', 'server_port', 'sni', 'upstream', 'upstream_id', 'service_id', 'plugins', 'snis', 'tls_passthrough'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'plugins' => 2];

    /**
     * @param array<string, string>|null $labels
     * @param array<string, array<string, mixed>>|null $plugins
     * @param list<string>|null $snis
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string|int|null $id = null,
        public ?string $name = null,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public ?string $remoteAddr = null,
        public ?string $serverAddr = null,
        public ?int $serverPort = null,
        public ?string $sni = null,
        public ?Upstream $upstream = null,
        public string|int|null $upstreamId = null,
        public string|int|null $serviceId = null,
        public ?array $plugins = null,
        public ?array $snis = null,
        public ?bool $tlsPassthrough = null,
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
            remoteAddr: $data->string('remote_addr'),
            serverAddr: $data->string('server_addr'),
            serverPort: $data->int('server_port'),
            sni: $data->string('sni'),
            upstream: $data->dto('upstream', Upstream::class),
            upstreamId: $data->id('upstream_id'),
            serviceId: $data->id('service_id'),
            plugins: $data->pluginMap('plugins'),
            snis: $data->stringList('snis'),
            tlsPassthrough: $data->bool('tls_passthrough'),
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
            'remote_addr' => $this->remoteAddr,
            'server_addr' => $this->serverAddr,
            'server_port' => $this->serverPort,
            'sni' => $this->sni,
            'upstream' => $this->upstream,
            'upstream_id' => $this->upstreamId,
            'service_id' => $this->serviceId,
            'plugins' => $this->plugins,
            'snis' => $this->snis,
            'tls_passthrough' => $this->tlsPassthrough,
        ];
    }
}
