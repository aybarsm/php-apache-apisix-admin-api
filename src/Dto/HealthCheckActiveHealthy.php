<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheckActiveHealthy`: Thresholds for considering a target healthy during active checks.
 */
final readonly class HealthCheckActiveHealthy implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheckActiveHealthy';

    public const array KEYS = ['interval', 'http_statuses', 'successes'];

    /**
     * @param list<int>|null $httpStatuses
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?int $interval = null,
        public ?array $httpStatuses = null,
        public ?int $successes = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            interval: $data->int('interval'),
            httpStatuses: $data->intList('http_statuses'),
            successes: $data->int('successes'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'interval' => $this->interval,
            'http_statuses' => $this->httpStatuses,
            'successes' => $this->successes,
        ];
    }
}
