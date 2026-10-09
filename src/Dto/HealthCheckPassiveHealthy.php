<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheckPassiveHealthy`: Thresholds for considering a target healthy based on real traffic responses.
 */
final readonly class HealthCheckPassiveHealthy implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheckPassiveHealthy';

    public const array KEYS = ['http_statuses', 'successes'];

    /**
     * @param list<int>|null $httpStatuses
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?array $httpStatuses = null,
        public ?int $successes = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            httpStatuses: $data->intList('http_statuses'),
            successes: $data->int('successes'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'http_statuses' => $this->httpStatuses,
            'successes' => $this->successes,
        ];
    }
}
