<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Application;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\AttributeScanner;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Output;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecDiff;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;

/**
 * Builds resources/apache-apisix-like dir with v1.0.0 (= v3.19.0) and a mutated v1.1.0.
 */
function syntheticSpecDir(): string
{
    $dir = sys_get_temp_dir().'/apisix-php-diff-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $spec = json_decode((string) file_get_contents(projectRoot('resources/apache-apisix/v3.19.0.json')), true, 512, JSON_THROW_ON_ERROR);
    $overrides = json_decode((string) file_get_contents(projectRoot('resources/apache-apisix/v3.19.0.overrides.json')), true, 512, JSON_THROW_ON_ERROR);
    $overrides['spec'] = 'v1.0.0.json';

    file_put_contents($dir.'/v1.0.0.json', json_encode($spec, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    file_put_contents($dir.'/v1.0.0.overrides.json', json_encode($overrides, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    unset($spec['paths']['/apisix/admin/protos/{id}']['delete']);                                       // removed + mapped
    $spec['paths']['/apisix/admin/widgets'] = ['get' => ['operationId' => 'listWidgets', 'tags' => ['Widgets'], 'responses' => ['200' => ['description' => 'ok']]]];
    $spec['paths']['/apisix/admin/routes']['get']['parameters'][] = ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']];
    $spec['components']['schemas']['Route']['properties']['timeout_ms'] = ['type' => 'integer'];
    $spec['components']['schemas']['Upstream']['properties']['type']['enum'][] = 'least_latency';
    $spec['components']['schemas']['Widget'] = ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]];
    $spec['paths']['/apisix/admin/consumers/{username}/credentials/{id}']['get']['responses']['200']['content']['application/json']['schema'] = ['$ref' => '#/components/schemas/CredentialEnvelope'];
    file_put_contents($dir.'/v1.1.0.json', json_encode($spec, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    return $dir;
}

beforeEach(function (): void {
    $this->dir = syntheticSpecDir();
    $this->loader = new SpecLoader($this->dir);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    rmdir($this->dir);
});

it('reports nothing between identical specs', function (): void {
    $diff = SpecDiff::compare($this->loader->load('1.0.0', false), $this->loader->load('1.0.0', false), $this->loader->load('1.0.0')->overrides);

    expect($diff->isEmpty())->toBeTrue();
});

it('reports operation, parameter and schema changes with their impact', function (): void {
    $diff = SpecDiff::compare(
        $this->loader->load('1.0.0', false),
        $this->loader->load('1.1.0', false),
        $this->loader->load('1.0.0')->overrides,
        AttributeScanner::forProject(projectRoot())->scan(),
        projectRoot('src/Dto'),
    );

    expect($diff->addedOperations)->toBe(['listWidgets'])
        ->and($diff->removedOperations)->toBe(['deleteProto'])
        ->and($diff->changedOperations['listRoutes'] ?? [])->toBe(['param added: query:sort'])
        ->and($diff->changedOperations['getCredential'] ?? [])->toBe(['response 200: $CredentialListEnvelope -> $CredentialEnvelope'])
        ->and($diff->addedSchemas)->toBe(['Widget'])
        ->and($diff->changedSchemas['Route'] ?? [])->toBe(['property added: timeout_ms integer'])
        ->and($diff->changedSchemas['Upstream'][0] ?? '')->toStartWith('property type: string enum["roundrobin","chash","ewma","least_conn"] -> string enum[')
        ->and($diff->brokenMappings)->toBe(['Protos::delete (deleteProto)'])
        ->and($diff->affectedDtos)->toBe(['Route', 'Upstream'])
        ->and($diff->overridesToRecheck)->toBe(['getCredential' => 'operation changed in v1.1.0']);
});

it('renders markdown and json through bin/spec diff', function (): void {
    $output = Output::buffered();
    $code = (new Application(projectRoot(), $this->loader))->run(['diff', '1.0.0', '1.1.0'], $output);

    expect($code)->toBe(0)
        ->and($output->contents())->toContain(
            '# APISIX Admin API spec diff: v1.0.0 -> v1.1.0',
            '## Operations added (implement or exclude) (1)',
            '- listWidgets',
            '## Impact: mapped methods whose operation was removed (1)',
            '- getCredential — operation changed in v1.1.0',
        );

    $json = Output::buffered();
    (new Application(projectRoot(), $this->loader))->run(['diff', '1.0.0', '--json'], $json);
    expect(json_decode($json->contents(), true, 512, JSON_THROW_ON_ERROR))->toHaveKeys(['from', 'to', 'addedOperations', 'overridesToRecheck'])
        ->and(json_decode($json->contents(), true)['to'])->toBe('1.1.0');
});

it('diffs the shipped spec against itself', function (): void {
    [$code, $out] = runSpec('diff', '3.19.0', '3.19.0');

    expect($code)->toBe(0)->and($out)->toContain('No structural changes.');
});
