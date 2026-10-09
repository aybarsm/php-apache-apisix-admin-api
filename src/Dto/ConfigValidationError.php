<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns\DtoBehaviour;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Override;

/**
 * Spec schema `ConfigValidationError`.
 */
final readonly class ConfigValidationError implements Dto
{
    use DtoBehaviour;

    public const string SCHEMA = 'ConfigValidationError';

    public const array KEYS = ['resource_type', 'resource_id', 'index', 'error'];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $error,
        public ?string $resourceType = null,
        public string|int|null $resourceId = null,
        public ?int $index = null,
        public array $extra = [],
    ) {}

    #[Override]
    public static function fromData(Data $data): static
    {
        return new self(
            error: $data->required($data->string('error'), 'error'),
            resourceType: $data->string('resource_type'),
            resourceId: self::resourceId($data),
            index: $data->int('index'),
            extra: $data->extra(self::KEYS),
        );
    }

    /**
     * Spec `oneOf` string|integer; an empty string means the invalid item has no id.
     */
    private static function resourceId(Data $data): string|int|null
    {
        $value = $data->mixed('resource_id');

        return is_string($value) || is_int($value) ? $value : $data->string('resource_id');
    }

    #[Override]
    protected function payload(): array
    {
        return [
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'index' => $this->index,
            'error' => $this->error,
        ];
    }
}
