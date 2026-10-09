<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model;

final readonly class OperationOverride
{
    /**
     * @param array<string, array<string, mixed>> $responses status => replacement schema
     */
    public function __construct(
        public ?string $rename,
        public array $responses,
        public string $issue,
        public string $actual,
        public string $evidence,
    ) {}
}
