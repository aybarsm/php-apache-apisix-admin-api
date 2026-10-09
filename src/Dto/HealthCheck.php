<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheck`: Health check configuration for monitoring upstream node availability. Active checks require at least the `active` field.
 */
final readonly class HealthCheck implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheck';

    public const array KEYS = ['active', 'passive'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?HealthCheckActive $active = null,
        public ?HealthCheckPassive $passive = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            active: $data->dto('active', HealthCheckActive::class),
            passive: $data->dto('passive', HealthCheckPassive::class),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'active' => $this->active,
            'passive' => $this->passive,
        ];
    }
}
