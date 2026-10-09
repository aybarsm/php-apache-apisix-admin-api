<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;

beforeEach(function (): void {
    $this->loader = SpecLoader::forProject(projectRoot());
});

it('discovers spec versions', function (): void {
    $versions = $this->loader->versions();

    expect($versions)->toContain('3.19.0')
        ->and($this->loader->latest())->toBe($versions[array_key_last($versions)]);
});

it('loads every operation of the latest spec', function (): void {
    $spec = $this->loader->load('3.19.0');

    expect($spec->operations)->toHaveCount(93)
        ->and($spec->implementable())->toHaveCount(87);
});

it('applies operationId renames from overrides', function (): void {
    $spec = $this->loader->load('3.19.0');

    expect($spec->operation('getId]'))->toBeNull()
        ->and($spec->operation('getStreamRoute'))->not->toBeNull()
        ->and($spec->operation('getStreamRoute')?->specId)->toBe('getId]')
        ->and($spec->operation('getStreamRoute')?->path)->toBe('/apisix/admin/stream_routes/{id}');
});

it('marks excluded operations', function (): void {
    $op = $this->loader->load('3.19.0')->operation('listGraphQLCostDecorations');

    expect($op?->isExcluded())->toBeTrue()
        ->and($op?->excluded)->toContain('API7');
});

it('merges path-level and operation-level parameters', function (): void {
    $op = $this->loader->load('3.19.0')->operation('patchRoutesSubPath');
    $names = array_map(fn ($p) => $p->in.':'.$p->name, $op?->parameters ?? []);

    expect($names)->toContain('path:id', 'path:sub_path', 'query:ttl');
});

it('resolves request and response schemas through refs', function (): void {
    $spec = $this->loader->load('3.19.0');
    $op = $spec->operation('listRoutes');

    expect($op?->responses['200'])->toBe(['$ref' => '#/components/schemas/RouteListEnvelope'])
        ->and($spec->refs->resolve($op?->responses['200'] ?? [])['required'] ?? null)->toBe(['total', 'list']);
});

it('inlines refs recursively', function (): void {
    $spec = $this->loader->load('3.19.0');
    $inlined = $spec->refs->inline(['$ref' => '#/components/schemas/RouteEnvelope']);

    expect($inlined['properties']['value']['properties']['uri']['type'] ?? null)->toBe('string');
});
