<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Attributes;

use Attribute;

/**
 * Maps a public client method to the OpenAPI operationId it implements.
 *
 * The id is the canonical one: the spec's operationId, or its `rename` from the
 * versioned overrides file. `bin/spec coverage` relies on this attribute.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class SpecOperation
{
    public function __construct(
        public string $operationId,
    ) {}
}
