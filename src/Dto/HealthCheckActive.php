<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HealthCheckType;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheckActive`: Active health check configuration. APISIX periodically sends probes to upstream nodes to determine their health status.
 */
final readonly class HealthCheckActive implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheckActive';

    public const array KEYS = ['type', 'timeout', 'concurrency', 'host', 'port', 'http_path', 'https_verify_certificate', 'req_headers', 'healthy', 'unhealthy'];

    /**
     * @param list<string>|null $reqHeaders
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?HealthCheckType $type = null,
        public int|float|null $timeout = null,
        public ?int $concurrency = null,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $httpPath = null,
        public ?bool $httpsVerifyCertificate = null,
        public ?array $reqHeaders = null,
        public ?HealthCheckActiveHealthy $healthy = null,
        public ?HealthCheckActiveUnhealthy $unhealthy = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            type: $data->enum('type', HealthCheckType::class),
            timeout: $data->number('timeout'),
            concurrency: $data->int('concurrency'),
            host: $data->string('host'),
            port: $data->int('port'),
            httpPath: $data->string('http_path'),
            httpsVerifyCertificate: $data->bool('https_verify_certificate'),
            reqHeaders: $data->stringList('req_headers'),
            healthy: $data->dto('healthy', HealthCheckActiveHealthy::class),
            unhealthy: $data->dto('unhealthy', HealthCheckActiveUnhealthy::class),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'type' => $this->type,
            'timeout' => $this->timeout,
            'concurrency' => $this->concurrency,
            'host' => $this->host,
            'port' => $this->port,
            'http_path' => $this->httpPath,
            'https_verify_certificate' => $this->httpsVerifyCertificate,
            'req_headers' => $this->reqHeaders,
            'healthy' => $this->healthy,
            'unhealthy' => $this->unhealthy,
        ];
    }
}
