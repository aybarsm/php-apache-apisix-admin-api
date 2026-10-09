<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `KeepalivePool`: Connection pool configuration for keepalive connections to upstream nodes.
 */
final readonly class KeepalivePool implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'KeepalivePool';

    public const array KEYS = ['size', 'idle_timeout', 'requests'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public ?int $size = null,
        public int|float|null $idleTimeout = null,
        public ?int $requests = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            size: $data->int('size'),
            idleTimeout: $data->number('idle_timeout'),
            requests: $data->int('requests'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'size' => $this->size,
            'idle_timeout' => $this->idleTimeout,
            'requests' => $this->requests,
        ];
    }
}
