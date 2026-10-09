<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `VaultSecret`: HashiCorp Vault secret-manager configuration.
 */
final readonly class VaultSecret implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'VaultSecret';

    public const array KEYS = ['uri', 'prefix', 'token', 'namespace', 'id', 'create_time', 'update_time'];

    public const array READ_ONLY = ['id', 'create_time', 'update_time'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $uri,
        public string $prefix,
        public string $token,
        public ?string $namespace = null,
        public ?string $id = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            uri: $data->required($data->string('uri'), 'uri'),
            prefix: $data->required($data->string('prefix'), 'prefix'),
            token: $data->required($data->string('token'), 'token'),
            namespace: $data->string('namespace'),
            id: $data->string('id'),
            createTime: $data->int('create_time'),
            updateTime: $data->int('update_time'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'uri' => $this->uri,
            'prefix' => $this->prefix,
            'token' => $this->token,
            'namespace' => $this->namespace,
            'id' => $this->id,
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
        ];
    }
}
