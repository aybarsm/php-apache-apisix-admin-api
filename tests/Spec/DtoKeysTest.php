<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\Naming;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

/**
 * Keeps every DTO aligned with its spec schema (the source of truth).
 */
it('mirrors the spec schema properties', function (string $class): void {
    $schema = SpecExamples::spec()->refs->resolve(SpecExamples::spec()->schemas()[$class::SCHEMA] ?? []);

    expect($schema)->not->toBe([], $class::SCHEMA.' is not a spec schema')
        ->and($class::KEYS)->toBe(array_keys($schema['properties'] ?? []));
})->with(fn (): array => dtoClasses());

it('declares constructor properties matching KEYS', function (string $class): void {
    $params = array_map(fn (ReflectionParameter $p): string => $p->getName(), (new ReflectionMethod($class, '__construct'))->getParameters());
    $expected = [...array_map(Naming::property(...), $class::KEYS), 'extra'];

    sort($params);
    sort($expected);
    expect($params)->toBe($expected);
})->with(fn (): array => dtoClasses());

it('only lists known keys as read-only or object keys', function (string $class): void {
    expect(array_diff($class::READ_ONLY, $class::KEYS))->toBe([])
        ->and(array_diff(array_keys($class::OBJECT_KEYS), $class::KEYS))->toBe([]);
})->with(fn (): array => dtoClasses());

it('marks spec readOnly properties as READ_ONLY', function (string $class): void {
    $schema = SpecExamples::spec()->refs->resolve(SpecExamples::spec()->schemas()[$class::SCHEMA]);
    $readOnly = array_keys(array_filter($schema['properties'] ?? [], fn ($p): bool => is_array($p) && ($p['readOnly'] ?? false) === true));

    expect($class::READ_ONLY)->toBe($readOnly);
})->with(fn (): array => dtoClasses());
