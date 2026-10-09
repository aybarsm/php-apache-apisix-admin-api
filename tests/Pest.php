<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test configuration
|--------------------------------------------------------------------------
| Suites (phpunit.xml): unit (tests/Unit, tests/Spec), arch (tests/Arch),
| integration (tests/Integration — needs APISIX_ADMIN_KEY).
*/

function projectRoot(string $path = ''): string
{
    return dirname(__DIR__).($path === '' ? '' : '/'.ltrim($path, '/'));
}
