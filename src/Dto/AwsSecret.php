<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `AwsSecret`: AWS Secrets Manager configuration.
 */
final readonly class AwsSecret implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'AwsSecret';

    public const array KEYS = ['access_key_id', 'secret_access_key', 'session_token', 'region', 'endpoint_url', 'id', 'create_time', 'update_time'];

    public const array READ_ONLY = ['id', 'create_time', 'update_time'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $accessKeyId,
        public string $secretAccessKey,
        public ?string $sessionToken = null,
        public ?string $region = null,
        public ?string $endpointUrl = null,
        public ?string $id = null,
        public ?int $createTime = null,
        public ?int $updateTime = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            accessKeyId: $data->required($data->string('access_key_id'), 'access_key_id'),
            secretAccessKey: $data->required($data->string('secret_access_key'), 'secret_access_key'),
            sessionToken: $data->string('session_token'),
            region: $data->string('region'),
            endpointUrl: $data->string('endpoint_url'),
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
            'access_key_id' => $this->accessKeyId,
            'secret_access_key' => $this->secretAccessKey,
            'session_token' => $this->sessionToken,
            'region' => $this->region,
            'endpoint_url' => $this->endpointUrl,
            'id' => $this->id,
            'create_time' => $this->createTime,
            'update_time' => $this->updateTime,
        ];
    }
}
