<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\DtoGenerator;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\Naming;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\ResourceGenerator;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

/**
 * Committed files the generators must reproduce byte-for-byte. Hand-edited files
 * (unions, merged enums, quirks) are intentionally absent from these lists.
 */
const UNEDITED_DTOS = [
    'Timeout', 'KeepalivePool', 'UpstreamTLS', 'UpstreamWarmUp', 'UpstreamNodeItem', 'HealthCheck',
    'HealthCheckActive', 'HealthCheckActiveHealthy', 'HealthCheckActiveUnhealthy',
    'HealthCheckPassive', 'HealthCheckPassiveHealthy', 'HealthCheckPassiveUnhealthy',
    'Route', 'Service', 'StreamRoute', 'Proto',
    'Consumer', 'Credential', 'ConsumerGroup', 'PluginConfig', 'GlobalRule',
    'SSL', 'VaultSecret', 'AwsSecret', 'GcpSecret',
];

function dtoGenerator(): DtoGenerator
{
    return new DtoGenerator(SpecExamples::spec(), DtoGenerator::discoverEnums(projectRoot('src/Enums')));
}

const UNEDITED_RESOURCES = [
    'Upstreams' => 'Upstreams',
    'Routes' => 'Routes',
    'Services' => 'Services',
    'Stream Routes' => 'StreamRoutes',
    'Protos' => 'Protos',
    'Consumer Groups' => 'ConsumerGroups',
    'Plugin Configs' => 'PluginConfigs',
    'Global Rules' => 'GlobalRules',
    'SSL Certificates' => 'Ssls',
];

it('names things consistently', function (): void {
    expect(Naming::className('UpstreamTLS'))->toBe('UpstreamTls')
        ->and(Naming::className('SSLRead'))->toBe('SslRead')
        ->and(Naming::className('SSL'))->toBe('Ssl')
        ->and(Naming::className('Stream_Routes'))->toBe('StreamRoutes')
        ->and(Naming::className('plugin_configs'))->toBe('PluginConfigs')
        ->and(Naming::property('create_time'))->toBe('createTime')
        ->and(Naming::enumCase('least_conn'))->toBe('LeastConn')
        ->and(Naming::enumCase(1))->toBe('Value1')
        ->and(Naming::enumCase('TLSv1.2'))->toBe('Tlsv12')
        ->and(Naming::enumCase(0, 'Disabled.'))->toBe('Disabled')
        ->and(Naming::enumCase(1, 'Enabled (default).'))->toBe('Enabled');
});

it('reproduces unedited DTOs', function (string $schema): void {
    $path = projectRoot('src/Dto/'.Naming::className($schema).'.php');

    expect(dtoGenerator()->dtoSource($schema))->toBe(file_get_contents($path));
})->with(UNEDITED_DTOS);

it('reproduces unedited resources', function (string $tag, string $class): void {
    $files = (new ResourceGenerator(SpecExamples::spec()))->files($tag, $class);

    expect(array_values($files)[0])->toBe(file_get_contents(projectRoot(array_key_first($files))));
})->with(fn (): array => array_map(null, array_keys(UNEDITED_RESOURCES), array_values(UNEDITED_RESOURCES)));

it('reuses existing enums by name or identical values', function (): void {
    $generator = dtoGenerator();

    expect($generator->dtoSource('HealthCheckPassive'))->toContain('?HealthCheckType $type')
        ->and($generator->dtoSource('Route'))->toContain('?Status $status')
        ->and($generator->enumSources('Route'))->toBe([])
        ->and((new DtoGenerator(SpecExamples::spec()))->enumSources('Route'))->toHaveKey('RouteStatus');
});

it('computes the nested schema closure', function (): void {
    expect((new DtoGenerator(SpecExamples::spec()))->closure('HealthCheck'))
        ->toBe(['HealthCheck', 'HealthCheckActive', 'HealthCheckActiveHealthy', 'HealthCheckActiveUnhealthy', 'HealthCheckPassive', 'HealthCheckPassiveHealthy', 'HealthCheckPassiveUnhealthy']);
});

it('marks unions as mixed with a TODO', function (): void {
    $source = (new DtoGenerator(SpecExamples::spec()))->dtoSource('Upstream');

    expect($source)->toContain('public mixed $nodes = null, // TODO: model the anyOf/oneOf union');
});

it('prints without writing unless --write is passed', function (): void {
    [$code, $out] = runSpec('scaffold', 'dto', 'Timeout');

    expect($code)->toBe(0)->and($out)->toContain('// ==> src/Dto/Timeout.php', 'final readonly class Timeout');
});

it('never overwrites existing files', function (): void {
    [$code, $out] = runSpec('scaffold', 'resource', 'Upstreams', '--write');

    expect($code)->toBe(0)->and($out)->toContain('skip   src/Resources/Upstreams.php (exists)');
});

it('emits TODO stubs for non-CRUD operations', function (): void {
    $source = (new ResourceGenerator(SpecExamples::spec()))->source('Plugins', 'Plugins');

    expect($source)->toContain("#[SpecOperation('reloadPlugins')]", "throw new LogicException('TODO: implement PUT /apisix/admin/plugins/reload');");
});
