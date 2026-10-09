<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\ClientConfig;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\Env;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\FakeHttpClient;
use GuzzleHttp\Psr7\HttpFactory;

/*
|--------------------------------------------------------------------------
| Test configuration
|--------------------------------------------------------------------------
| Suites (phpunit.xml): unit (tests/Unit, tests/Spec), arch (tests/Arch),
| integration (tests/Integration — needs a reachable APISIX and APISIX_ADMIN_KEY).
*/

const TEST_API_KEY = 'test-admin-key';

const TEST_BASE_URI = 'http://apisix.test:9180';

function projectRoot(string $path = ''): string
{
    return dirname(__DIR__).($path === '' ? '' : '/'.ltrim($path, '/'));
}

function fakeHttp(): FakeHttpClient
{
    return new FakeHttpClient();
}

function fakeClient(FakeHttpClient $http): ApiClient
{
    return ApiClient::create(TEST_BASE_URI, TEST_API_KEY, $http);
}

function fakeTransport(FakeHttpClient $http, string $apiKey = TEST_API_KEY): Transport
{
    $factory = new HttpFactory();

    return new Transport(new ClientConfig(TEST_BASE_URI, $apiKey), $http, $factory, $factory);
}

function jsonFixture(string $name): array
{
    return json_decode((string) file_get_contents(projectRoot('tests/fixtures/'.$name.'.json')), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Live client for integration tests; skips the test when APISIX is unreachable or no key is configured.
 */
function liveClient(bool $requireKey = true): ApiClient
{
    static $reachable = null;
    $reachable ??= (function (): bool {
        $parts = parse_url(Env::adminUrl());
        $socket = @fsockopen((string) ($parts['host'] ?? ''), (int) ($parts['port'] ?? 80), $errno, $errstr, 2.0);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    })();

    if (! $reachable) {
        test()->markTestSkipped(sprintf('APISIX Admin API not reachable at %s.', Env::adminUrl()));
    }

    $key = Env::adminKey();
    if ($requireKey && ($key === null || $key === '')) {
        test()->markTestSkipped('APISIX_ADMIN_KEY is not set (export it or add it to .env).');
    }

    return ApiClient::create(Env::adminUrl(), (string) $key);
}

/**
 * Unique, spec-valid identifier for integration fixtures.
 */
function liveId(string $prefix = 'it'): string
{
    return $prefix.'-'.bin2hex(random_bytes(4));
}
