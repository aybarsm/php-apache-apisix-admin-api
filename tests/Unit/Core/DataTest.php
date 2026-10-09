<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Timeout;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\HydrationException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;

it('reads scalars leniently where JSON numbers allow it', function (): void {
    $d = Data::of(['s' => 'x', 'i' => 3, 'fi' => 4.0, 'f' => 1.5, 'b' => false, 'n' => 2], 'T');

    expect($d->string('s'))->toBe('x')
        ->and($d->int('i'))->toBe(3)
        ->and($d->int('fi'))->toBe(4)
        ->and($d->float('f'))->toBe(1.5)
        ->and($d->float('i'))->toBe(3.0)
        ->and($d->number('n'))->toBe(2)
        ->and($d->number('f'))->toBe(1.5)
        ->and($d->bool('b'))->toBeFalse()
        ->and($d->string('missing'))->toBeNull()
        ->and($d->has('s'))->toBeTrue()
        ->and($d->has('missing'))->toBeFalse();
});

it('reads ids, lists and maps', function (): void {
    $d = Data::of(['a' => 'r1', 'b' => 5, 'l' => ['x', 'y'], 'il' => [1, 2], 'm' => ['k' => 'v'], 'e' => [], 'p' => ['limit-count' => ['count' => 1], 'prometheus' => []]], 'T');

    expect($d->id('a'))->toBe('r1')
        ->and($d->id('b'))->toBe(5)
        ->and($d->stringList('l'))->toBe(['x', 'y'])
        ->and($d->intList('il'))->toBe([1, 2])
        ->and($d->stringMap('m'))->toBe(['k' => 'v'])
        ->and($d->map('e'))->toBe([])
        ->and($d->pluginMap('p'))->toBe(['limit-count' => ['count' => 1], 'prometheus' => []]);
});

it('hydrates nested DTOs, DTO lists and enums', function (): void {
    $d = Data::of(['t' => ['connect' => 1, 'send' => 2, 'read' => 3], 'ts' => [['connect' => 1, 'send' => 1, 'read' => 1]], 'm' => 'GET'], 'T');

    expect($d->dto('t', Timeout::class)?->send)->toBe(2)
        ->and($d->dtoList('ts', Timeout::class))->toHaveCount(1)
        ->and($d->enum('m', HttpMethod::class))->toBe(HttpMethod::Get)
        ->and($d->dto('none', Timeout::class))->toBeNull();
});

it('reports the JSON path of type mismatches', function (Closure $read, string $message): void {
    $d = Data::of(['s' => 1, 'i' => 'x', 'f' => 1.5, 'l' => ['a', 2], 'm' => ['k' => 1], 'o' => [1, 2], 'id' => 0, 'e' => 'NOPE', 't' => ['connect' => 1]], 'Route');

    expect(fn () => $read($d))->toThrow(HydrationException::class, $message);
})->with([
    'string' => [fn (Data $d) => $d->string('s'), 'Route.s: expected string, got int'],
    'int' => [fn (Data $d) => $d->int('i'), 'Route.i: expected integer, got string'],
    'non-integral int' => [fn (Data $d) => $d->int('f'), 'Route.f: expected integer'],
    'string list' => [fn (Data $d) => $d->stringList('l'), 'Route.l[1]: expected string'],
    'string map' => [fn (Data $d) => $d->stringMap('m'), 'Route.m.k: expected string'],
    'object' => [fn (Data $d) => $d->map('o'), 'Route.o: expected an object'],
    'id' => [fn (Data $d) => $d->id('id'), 'Route.id: expected resource id'],
    'enum' => [fn (Data $d) => $d->enum('e', HttpMethod::class), 'Route.e: unsupported'],
    'required' => [fn (Data $d) => $d->requiredString('missing'), 'Route.missing: required value is missing'],
    'nested' => [fn (Data $d) => $d->dto('t', Timeout::class), 'Route.t.send: required value is missing'],
]);

it('collects unknown keys as extra', function (): void {
    expect(Data::of(['a' => 1, 'b' => 2, 'c' => 3], 'T')->extra(['a']))->toBe(['b' => 2, 'c' => 3]);
});
