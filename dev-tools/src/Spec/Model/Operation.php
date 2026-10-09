<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model;

final readonly class Operation
{
    /**
     * @param list<string>                             $tags
     * @param list<Parameter>                          $parameters path-level merged with operation-level
     * @param array<string, mixed>|null                $requestSchema raw schema (refs preserved)
     * @param array<string, array<string, mixed>|null> $responses status => raw schema (null when no JSON body)
     */
    public function __construct(
        public string $id,
        public string $specId,
        public string $method,
        public string $path,
        public array $tags,
        public string $summary,
        public array $parameters,
        public ?array $requestSchema,
        public array $responses,
        public ?string $excluded = null,
        public ?OperationOverride $override = null,
    ) {}

    public function isExcluded(): bool
    {
        return $this->excluded !== null;
    }

    public function tag(): string
    {
        return $this->tags[0] ?? 'Untagged';
    }

    /**
     * @return list<Parameter>
     */
    public function parametersIn(string $in): array
    {
        return array_values(array_filter($this->parameters, static fn (Parameter $p): bool => $p->in === $in));
    }
}
