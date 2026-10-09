<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `UpstreamWarmUp`: Gradually increases the effective weight of newly observed nodes. Only supported for HTTP upstreams using `roundrobin` and a single node priority; ignored by stream routes and rejected in inline `traffic-split` upstreams. The initial node set is already warmed. Each gateway instance tracks ramps independently. This changes traffic distribution, not the total request rate.
 */
final readonly class UpstreamWarmUp implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'UpstreamWarmUp';

    public const array KEYS = ['slow_start_time_seconds', 'min_weight_percent', 'interval', 'aggression', 'startup_grace_period_seconds'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public int $slowStartTimeSeconds,
        public int $minWeightPercent,
        public ?int $interval = null,
        public int|float|null $aggression = null,
        public ?int $startupGracePeriodSeconds = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            slowStartTimeSeconds: $data->required($data->int('slow_start_time_seconds'), 'slow_start_time_seconds'),
            minWeightPercent: $data->required($data->int('min_weight_percent'), 'min_weight_percent'),
            interval: $data->int('interval'),
            aggression: $data->number('aggression'),
            startupGracePeriodSeconds: $data->int('startup_grace_period_seconds'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'slow_start_time_seconds' => $this->slowStartTimeSeconds,
            'min_weight_percent' => $this->minWeightPercent,
            'interval' => $this->interval,
            'aggression' => $this->aggression,
            'startup_grace_period_seconds' => $this->startupGracePeriodSeconds,
        ];
    }
}
