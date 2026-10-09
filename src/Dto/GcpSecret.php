<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `GcpSecret`: Google Cloud Secret Manager configuration.
 */
final readonly class GcpSecret implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'GcpSecret';

    public const array KEYS = ['auth_config', 'ssl_verify', 'auth_file', 'id', 'create_time', 'update_time'];

    public const array READ_ONLY = ['id', 'create_time', 'update_time'];

    public const array OBJECT_KEYS = ['auth_config' => 1];

    /**
     * @param array<string, mixed>|null $authConfig
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?array $authConfig = null,
        public ?bool $sslVerify = null,
        public ?string $authFile = null,
        public ?string $id = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            authConfig: $data->map('auth_config'),
            sslVerify: $data->bool('ssl_verify'),
            authFile: $data->string('auth_file'),
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
            'auth_config' => $this->authConfig,
            'ssl_verify' => $this->sslVerify,
            'auth_file' => $this->authFile,
            'id' => $this->id,
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
        ];
    }
}
