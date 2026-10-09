<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheckActiveUnhealthy`: Thresholds for considering a target unhealthy during active checks.
 */
final readonly class HealthCheckActiveUnhealthy implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheckActiveUnhealthy';

    public const array KEYS = ['interval', 'http_statuses', 'http_failures', 'tcp_failures', 'timeouts'];

    /**
     * @param list<int>|null $httpStatuses
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?int $interval = null,
        public ?array $httpStatuses = null,
        public ?int $httpFailures = null,
        public ?int $tcpFailures = null,
        public ?int $timeouts = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            interval: $data->int('interval'),
            httpStatuses: $data->intList('http_statuses'),
            httpFailures: $data->int('http_failures'),
            tcpFailures: $data->int('tcp_failures'),
            timeouts: $data->int('timeouts'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'interval' => $this->interval,
            'http_statuses' => $this->httpStatuses,
            'http_failures' => $this->httpFailures,
            'tcp_failures' => $this->tcpFailures,
            'timeouts' => $this->timeouts,
        ];
    }
}
