<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Tests\Support\Fixtures;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Timeout;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;

/**
 * Exercises every DtoBehaviour feature without depending on a real schema.
 */
final readonly class SampleDto implements Dto
{
    use DtoBehaviour;

    public const array KEYS = ['id', 'name', 'labels', 'plugins', 'timeout', 'method', 'timeouts', 'create_time'];

    public const array READ_ONLY = ['create_time'];

    public const array OBJECT_KEYS = ['labels' => 1, 'plugins' => 2];

    /**
     * @param array<string, string>|null               $labels
     * @param array<string, array<string, mixed>>|null $plugins
     * @param list<Timeout>|null                       $timeouts
     * @param array<string, mixed>                     $extra
     */
    public function __construct(
        public string|int|null $id = null,
        public ?string $name = null,
        public ?array $labels = null,
        public ?array $plugins = null,
        public ?Timeout $timeout = null,
        public ?HttpMethod $method = null,
        public ?array $timeouts = null,
        public ?int $createTime = null,
        public array $extra = [],
    ) {}

    #[\Override]
    public static function fromData(Data $data): static
    {
        return new self(
            id: $data->id('id'),
            name: $data->string('name'),
            labels: $data->stringMap('labels'),
            plugins: $data->pluginMap('plugins'),
            timeout: $data->dto('timeout', Timeout::class),
            method: $data->enum('method', HttpMethod::class),
            timeouts: $data->dtoList('timeouts', Timeout::class),
            createTime: $data->int('create_time'),
            extra: $data->extra(self::KEYS),
        );
    }

    #[\Override]
    protected function payload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'labels' => $this->labels,
            'plugins' => $this->plugins,
            'timeout' => $this->timeout,
            'method' => $this->method,
            'timeouts' => $this->timeouts,
            'create_time' => $this->createTime,
        ];
    }
}
