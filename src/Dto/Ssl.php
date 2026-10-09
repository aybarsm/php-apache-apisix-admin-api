<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\SslType;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Status;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `SSL`: SSL certificate configuration for TLS termination or mTLS.
 */
final readonly class Ssl implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'SSL';

    public const array KEYS = ['id', 'desc', 'labels', 'create_time', 'update_time', 'type', 'sni', 'snis', 'cert', 'key', 'certs', 'keys', 'client', 'status', 'ssl_protocols'];

    public const array READ_ONLY = ['create_time', 'update_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'client' => 1];

    /**
     * @param array<string, string>|null $labels
     * @param list<string>|null $snis
     * @param list<string>|null $certs
     * @param list<string>|null $keys
     * @param array<string, mixed>|null $client
     * @param list<string>|null $sslProtocols
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string|int|null $id = null,
        public ?string $desc = null,
        public ?array $labels = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public ?SslType $type = null,
        public ?string $sni = null,
        public ?array $snis = null,
        public ?string $cert = null,
        public ?string $key = null,
        public ?array $certs = null,
        public ?array $keys = null,
        public ?array $client = null,
        public ?Status $status = null,
        public ?array $sslProtocols = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            id: $data->id('id'),
            desc: $data->string('desc'),
            labels: $data->stringMap('labels'),
            createTime: $data->int('create_time'),
            updateTime: $data->int('update_time'),
            type: $data->enum('type', SslType::class),
            sni: $data->string('sni'),
            snis: $data->stringList('snis'),
            cert: $data->string('cert'),
            key: $data->string('key'),
            certs: $data->stringList('certs'),
            keys: $data->stringList('keys'),
            client: $data->map('client'),
            status: $data->enum('status', Status::class),
            sslProtocols: $data->stringList('ssl_protocols'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'id' => $this->id,
            'desc' => $this->desc,
            'labels' => $this->labels,
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
            'type' => $this->type,
            'sni' => $this->sni,
            'snis' => $this->snis,
            'cert' => $this->cert,
            'key' => $this->key,
            'certs' => $this->certs,
            'keys' => $this->keys,
            'client' => $this->client,
            'status' => $this->status,
            'ssl_protocols' => $this->sslProtocols,
        ];
    }
}
