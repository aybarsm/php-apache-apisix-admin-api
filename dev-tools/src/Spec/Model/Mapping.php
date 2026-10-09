<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model;

final readonly class Mapping
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $operationId,
        public string $class,
        public string $method,
    ) {}

    public function target(): string
    {
        $short = substr($this->class, (int) strrpos($this->class, '\\') + 1);

        return $short.'::'.$this->method;
    }
}
