<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto\Concerns;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Aybarsm\Apache\Apisix\AdminApi\Internal\JsonShape;
use Override;
use ReflectionClass;

/**
 * Shared DTO plumbing. The using class declares promoted constructor properties
 * (including `public array $extra`) and implements `payload()` and `fromData()`.
 *
 * @phpstan-require-implements Dto
 */
trait DtoBehaviour
{
    /**
     * JSON key => value; values may be scalars, arrays, enums, DTOs or lists of DTOs.
     *
     * @return array<string, mixed>
     */
    abstract protected function payload(): array;

    #[Override]
    public static function fromArray(array $data): static
    {
        $pos = strrpos(static::class, '\\');

        return static::fromData(Data::of($data, $pos === false ? static::class : substr(static::class, $pos + 1)));
    }

    #[Override]
    public function toArray(): array
    {
        $out = [];
        foreach ($this->filledPayload() as $key => $value) {
            $out[$key] = JsonShape::plain($value);
        }

        return $out;
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return JsonShape::apply($this->filledPayload(), static::OBJECT_KEYS);
    }

    #[Override]
    public function with(mixed ...$changes): static
    {
        $current = get_object_vars($this);
        foreach (array_keys($changes) as $name) {
            if (! is_string($name) || ! array_key_exists($name, $current)) {
                throw new InvalidArgumentException(sprintf('%s::with() received unknown property "%s".', static::class, $name));
            }
        }

        return (new ReflectionClass($this))->newInstanceArgs([...$current, ...$changes]);
    }

    #[Override]
    public function toRequest(): array
    {
        return array_diff_key($this->jsonSerialize(), array_flip(static::READ_ONLY));
    }

    /**
     * Payload without nulls, followed by preserved unknown keys.
     *
     * @return array<string, mixed>
     */
    private function filledPayload(): array
    {
        $payload = array_filter($this->payload(), static fn (mixed $v): bool => $v !== null);

        return $payload + $this->extra;
    }
}
