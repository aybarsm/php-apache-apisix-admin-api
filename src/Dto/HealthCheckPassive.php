<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HealthCheckType;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheckPassive`: Passive health check configuration. APISIX monitors real traffic responses to determine upstream node health — no extra probes are sent.
 */
final readonly class HealthCheckPassive implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheckPassive';

    public const array KEYS = ['type', 'healthy', 'unhealthy'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?HealthCheckType $type = null,
        public ?HealthCheckPassiveHealthy $healthy = null,
        public ?HealthCheckPassiveUnhealthy $unhealthy = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            type: $data->enum('type', HealthCheckType::class),
            healthy: $data->dto('healthy', HealthCheckPassiveHealthy::class),
            unhealthy: $data->dto('unhealthy', HealthCheckPassiveUnhealthy::class),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'type' => $this->type,
            'healthy' => $this->healthy,
            'unhealthy' => $this->unhealthy,
        ];
    }
}
