<?php

declare(strict_types=1);

arch('source uses strict types')
    ->expect('Aybarsm\Apache\Apisix\AdminApi')
    ->toUseStrictTypes();

arch('dev tooling uses strict types')
    ->expect('Aybarsm\Apache\Apisix\AdminApi\Dev')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();
