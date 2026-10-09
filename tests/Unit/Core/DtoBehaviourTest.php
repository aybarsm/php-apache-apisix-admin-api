<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Timeout;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\Fixtures\SampleDto;

$payload = [
    'id' => 'r1',
    'name' => 'demo',
    'labels' => ['env' => 'dev'],
    'plugins' => ['limit-count' => ['count' => 2], 'prometheus' => []],
    'timeout' => ['connect' => 1, 'send' => 2.5, 'read' => 3],
    'method' => 'GET',
    'timeouts' => [['connect' => 1, 'send' => 1, 'read' => 1]],
    'create_time' => 1700000000,
    'unknown_field' => ['nested' => true],
];

it('round-trips losslessly through fromArray/toArray', function () use ($payload): void {
    expect(SampleDto::fromArray($payload)->toArray())->toEqual($payload);
});

it('hydrates typed properties', function () use ($payload): void {
    $dto = SampleDto::fromArray($payload);

    expect($dto->timeout)->toBeInstanceOf(Timeout::class)
        ->and($dto->timeout?->send)->toBe(2.5)
        ->and($dto->method)->toBe(HttpMethod::Get)
        ->and($dto->createTime)->toBe(1700000000)
        ->and($dto->extra)->toBe(['unknown_field' => ['nested' => true]]);
});

it('omits nulls when serialising', function (): void {
    expect((new SampleDto(name: 'x'))->toArray())->toBe(['name' => 'x']);
});

it('keeps empty JSON objects as objects when encoding', function (): void {
    $dto = new SampleDto(labels: [], plugins: ['prometheus' => [], 'limit-count' => ['count' => 1]]);

    expect(json_encode($dto, JSON_THROW_ON_ERROR))->toBe('{"labels":{},"plugins":{"prometheus":{},"limit-count":{"count":1}}}');
});

it('encodes nested DTOs and enums', function (): void {
    $dto = new SampleDto(timeout: new Timeout(1, 2, 3), method: HttpMethod::Post);

    expect(json_encode($dto, JSON_THROW_ON_ERROR))->toBe('{"timeout":{"connect":1,"send":2,"read":3},"method":"POST"}');
});

it('drops read-only keys from request bodies', function (): void {
    $dto = new SampleDto(name: 'x', createTime: 1);

    expect($dto->toRequest())->toBe(['name' => 'x'])
        ->and($dto->toArray())->toBe(['name' => 'x', 'create_time' => 1]);
});

it('does not let extra keys shadow known ones', function (): void {
    $dto = new SampleDto(name: 'real', extra: ['name' => 'shadow', 'other' => 1]);

    expect($dto->toArray())->toBe(['name' => 'real', 'other' => 1]);
});

it('creates modified copies with with()', function (): void {
    $original = new SampleDto(name: 'a', labels: ['k' => 'v']);
    $copy = $original->with(name: 'b');

    expect($copy)->not->toBe($original)
        ->and($copy->name)->toBe('b')
        ->and($copy->labels)->toBe(['k' => 'v'])
        ->and($original->name)->toBe('a');
});

it('rejects unknown or positional with() arguments', function (): void {
    $dto = new SampleDto();

    expect(fn () => $dto->with(nope: 1))->toThrow(InvalidArgumentException::class, 'unknown property "nope"')
        ->and(fn () => $dto->with(1))->toThrow(InvalidArgumentException::class, 'unknown property "0"');
});

it('round-trips the Timeout DTO', function (): void {
    $data = ['connect' => 6, 'send' => 6, 'read' => 6.5];

    expect(Timeout::fromArray($data)->toArray())->toBe($data)
        ->and(json_encode(Timeout::fromArray($data), JSON_THROW_ON_ERROR))->toBe('{"connect":6,"send":6,"read":6.5}');
});
