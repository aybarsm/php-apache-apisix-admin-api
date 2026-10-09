<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `HealthCheckPassiveUnhealthy`: Thresholds for considering a target unhealthy based on real traffic responses.
 */
final readonly class HealthCheckPassiveUnhealthy implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'HealthCheckPassiveUnhealthy';

    public const array KEYS = ['http_statuses', 'tcp_failures', 'timeouts', 'http_failures'];

    /**
     * @param list<int>|null $httpStatuses
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?array $httpStatuses = null,
        public ?int $tcpFailures = null,
        public ?int $timeouts = null,
        public ?int $httpFailures = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            httpStatuses: $data->intList('http_statuses'),
            tcpFailures: $data->int('tcp_failures'),
            timeouts: $data->int('timeouts'),
            httpFailures: $data->int('http_failures'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'http_statuses' => $this->httpStatuses,
            'tcp_failures' => $this->tcpFailures,
            'timeouts' => $this->timeouts,
            'http_failures' => $this->httpFailures,
        ];
    }
}
