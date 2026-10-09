<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts;

use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use JsonSerializable;

/**
 * Immutable representation of an APISIX Admin API schema object.
 *
 * Implementations are `final readonly` classes whose promoted constructor
 * properties mirror the spec schema (camelCase), plus `array $extra` for keys
 * the schema does not describe.
 */
interface Dto extends JsonSerializable
{
    /**
     * JSON keys described by the schema, in spec order.
     *
     * @var list<string>
     */
    public const array KEYS = [];

    /**
     * Keys managed by APISIX; dropped from request bodies.
     *
     * @var list<string>
     */
    public const array READ_ONLY = [];

    /**
     * Keys whose value is a JSON object (key => nesting depth that must stay an object when empty).
     *
     * @var array<string, int>
     */
    public const array OBJECT_KEYS = [];

    /**
     * @param array<array-key, mixed> $data decoded JSON object (snake_case keys)
     */
    public static function fromArray(array $data): static;

    /**
     * @internal hydration entry point used by nested DTOs
     */
    public static function fromData(Data $data): static;

    /**
     * Plain PHP array (snake_case keys, nested DTOs and enums flattened, nulls omitted).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Same as {@see toArray()} but empty JSON objects survive encoding as `{}`.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;

    /**
     * Copy with the given (named) properties replaced.
     */
    public function with(mixed ...$changes): static;

    /**
     * Request body: the JSON payload without {@see self::READ_ONLY} keys.
     *
     * @return array<string, mixed>
     */
    public function toRequest(): array;
}
