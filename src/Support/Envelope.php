<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Support;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;

/**
 * etcd-style wrapper around a stored resource: `{key, value, createdIndex, modifiedIndex}`.
 *
 * @template-covariant T of Dto
 */
final readonly class Envelope
{
    /**
     * @param T $value
     */
    public function __construct(
        public string $key,
        public Dto $value,
        public ?int $createdIndex = null,
        public ?int $modifiedIndex = null,
    ) {}

    /**
     * @template D of Dto
     *
     * @param array<array-key, mixed> $data
     * @param class-string<D>         $class
     *
     * @return self<D>
     */
    public static function fromArray(array $data, string $class, string $path = 'Envelope'): self
    {
        $x = Data::of($data, $path);

        return new self(
            key: $x->requiredString('key'),
            value: $x->requiredDto('value', $class),
            createdIndex: $x->int('createdIndex'),
            modifiedIndex: $x->int('modifiedIndex'),
        );
    }

    /**
     * Last segment of the storage key, i.e. the resource id (or username).
     */
    public function id(): string
    {
        $pos = strrpos($this->key, '/');

        return $pos === false ? $this->key : substr($this->key, $pos + 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'value' => $this->value->toArray(),
            'createdIndex' => $this->createdIndex,
            'modifiedIndex' => $this->modifiedIndex,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
