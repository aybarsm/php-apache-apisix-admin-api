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

/**
 * Recursively sorts associative arrays by key so strict comparisons ignore key order.
 */
function canonical(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    $value = array_map(canonical(...), $value);
    if (! array_is_list($value)) {
        ksort($value);
    }

    return $value;
}

/**
 * Envelope list response with `$count` items out of `$total`.
 */
function listResponse(int $total, int $count, string $resource = 'upstreams'): array
{
    $list = [];
    for ($i = 0; $i < $count; $i++) {
        $id = 'id-'.bin2hex(random_bytes(3));
        $list[] = ['key' => "/apisix/{$resource}/{$id}", 'value' => ['id' => $id, 'nodes' => ['127.0.0.1:80' => 1]], 'createdIndex' => 1, 'modifiedIndex' => 1];
    }

    return ['total' => $total, 'list' => $list];
}

/**
 * Every concrete DTO class under src/Dto that mirrors a spec schema.
 *
 * @return list<class-string<Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto>>
 */
function dtoClasses(): array
{
    $out = [];
    foreach (Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SourceClasses::inNamespace('Dto') as $class) {
        if ($class->isInstantiable() && $class->implementsInterface(Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto::class) && $class->getConstant('SCHEMA') !== '') {
            $out[] = $class->getName();
        }
    }

    return $out;
}

/**
 * Runs `bin/spec` in-process.
 *
 * @return array{0: int, 1: string} exit code and output
 */
function runSpec(string ...$argv): array
{
    $output = Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output::buffered();
    $code = (new Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Application(projectRoot()))->run(array_values($argv), $output);

    return [$code, $output->contents()];
}
