<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model;

final readonly class SchemaOverride
{
    /**
     * @param array<string, array<string, mixed>> $properties properties added to (or replacing those of) the component schema
     */
    public function __construct(
        public array $properties,
        public string $issue,
        public string $actual,
        public string $evidence,
    ) {}
}
