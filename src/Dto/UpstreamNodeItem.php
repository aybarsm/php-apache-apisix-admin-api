<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `UpstreamNodeItem`: An upstream node with explicit host, port, weight, and optional priority.
 */
final readonly class UpstreamNodeItem implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'UpstreamNodeItem';

    public const array KEYS = ['host', 'port', 'weight', 'priority', 'metadata'];

    public const array OBJECT_KEYS = ['metadata' => 1];

    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $host,
        public int $weight,
        public ?int $port = null,
        public ?int $priority = null,
        public ?array $metadata = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            host: $data->required($data->string('host'), 'host'),
            weight: $data->required($data->int('weight'), 'weight'),
            port: $data->int('port'),
            priority: $data->int('priority'),
            metadata: $data->map('metadata'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'weight' => $this->weight,
            'priority' => $this->priority,
            'metadata' => $this->metadata,
        ];
    }
}
