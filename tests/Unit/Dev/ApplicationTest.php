<?php

declare(strict_types=1);


it('prints help', function (): void {
    [$code, $out] = runSpec('help');

    expect($code)->toBe(0)->and($out)->toContain('ops', 'coverage');
});

it('fails on unknown commands', function (): void {
    [$code] = runSpec('nope');

    expect($code)->toBe(1);
});

it('lists operations with a summary', function (): void {
    [$code, $out] = runSpec('ops', '--spec=3.19.0');

    expect($code)->toBe(0)
        ->and($out)->toContain('v3.19.0: 93 operations')
        ->and($out)->toContain('6 excluded');
});

it('filters operations by tag', function (): void {
    [, $out] = runSpec('ops', '--tag=Protos');

    expect($out)->toContain('listProtos')->not->toContain('listRoutes');
});

it('emits json', function (): void {
    [, $out] = runSpec('ops', '--json', '--tag=Protos');
    $decoded = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()->each->toHaveKeys(['id', 'specId', 'method', 'path', 'mapping']);
});

it('reports errors for unknown spec versions', function (): void {
    [$code, $out] = runSpec('ops', '--spec=0.0.0');

    expect($code)->toBe(1)->and($out)->toContain('error: File not found');
});
