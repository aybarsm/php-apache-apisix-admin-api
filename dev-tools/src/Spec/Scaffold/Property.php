<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold;

/**
 * Generator model of one DTO property.
 */
final readonly class Property
{
    /**
     * @param string      $nativeType PHP type without nullability, e.g. `string|int`, `?` handled by $required
     * @param string|null $docType    PHPDoc type when more precise than the native type
     * @param string      $accessor   `Data` call template, `%s` is the JSON key
     * @param string|null $dependency schema name of a nested DTO
     * @param array{name: string, backing: string, values: list<string|int>}|null $enum
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $nativeType,
        public ?string $docType,
        public string $accessor,
        public bool $required,
        public bool $readOnly = false,
        public int $objectDepth = 0,
        public ?string $dependency = null,
        public ?array $enum = null,
        public ?string $todo = null,
    ) {}

    public function declaredType(): string
    {
        if ($this->required) {
            return $this->nativeType;
        }

        return str_contains($this->nativeType, '|') || $this->nativeType === 'mixed'
            ? ($this->nativeType === 'mixed' ? 'mixed' : $this->nativeType.'|null')
            : '?'.$this->nativeType;
    }

    public function hydration(): string
    {
        $call = sprintf($this->accessor, var_export($this->key, true));

        return $this->required && ! str_starts_with($this->accessor, '$data->required')
            ? sprintf('$data->required(%s, %s)', $call, var_export($this->key, true))
            : $call;
    }
}
