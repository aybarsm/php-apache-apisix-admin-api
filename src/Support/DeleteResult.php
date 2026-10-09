<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Support;

use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;

/**
 * Result of a DELETE: `{key, deleted}`.
 */
final readonly class DeleteResult
{
    public function __construct(
        public string $key,
        public string $deleted,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $x = Data::of($data, 'DeleteResult');
        $deleted = $x->mixed('deleted');

        return new self(
            key: $x->string('key') ?? '',
            deleted: is_int($deleted) || is_string($deleted) ? (string) $deleted : '',
        );
    }
}
