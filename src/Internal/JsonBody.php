<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

/**
 * A request payload to be JSON-encoded. Wrapping distinguishes "no body" from a JSON `null` body.
 *
 * @internal
 */
final readonly class JsonBody
{
    public function __construct(
        public mixed $value,
    ) {}
}
