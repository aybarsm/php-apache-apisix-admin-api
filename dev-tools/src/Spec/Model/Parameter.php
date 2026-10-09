<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model;

final readonly class Parameter
{
    /**
     * @param array<string, mixed> $schema raw schema (refs preserved)
     */
    public function __construct(
        public string $name,
        public string $in,
        public bool $required,
        public array $schema,
    ) {}
}
