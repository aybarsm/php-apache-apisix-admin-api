<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Commands;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Args;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output;

interface Command
{
    public const int SUCCESS = 0;

    public const int FAILURE = 1;

    public function name(): string;

    public function usage(): string;

    public function description(): string;

    public function run(Args $args, Output $output): int;
}
