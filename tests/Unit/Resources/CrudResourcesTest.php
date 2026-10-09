<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Resources\AbstractResource;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

/**
 * Request shape of every CRUD verb, for every etcd-backed resource.
 *
 * name => [accessor, collection path, minimal stored value, DTO class]
 */
dataset('crud resources', [
    'routes' => [fn (ApiClient $c) => $c->routes(), 'routes', ['uri' => '/x'], Aybarsm\Apache\Apisix\AdminApi\Dto\Route::class],
    'services' => [fn (ApiClient $c) => $c->services(), 'services', ['name' => 'x'], Aybarsm\Apache\Apisix\AdminApi\Dto\Service::class],
    'upstreams' => [fn (ApiClient $c) => $c->upstreams(), 'upstreams', ['nodes' => ['127.0.0.1:80' => 1]], Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream::class],
    'stream routes' => [fn (ApiClient $c) => $c->streamRoutes(), 'stream_routes', ['server_port' => 9100], Aybarsm\Apache\Apisix\AdminApi\Dto\StreamRoute::class],
    'protos' => [fn (ApiClient $c) => $c->protos(), 'protos', ['content' => 'syntax = "proto3";'], Aybarsm\Apache\Apisix\AdminApi\Dto\Proto::class],
    'consumer groups' => [fn (ApiClient $c) => $c->consumerGroups(), 'consumer_groups', ['plugins' => ['limit-count' => ['count' => 1]]], Aybarsm\Apache\Apisix\AdminApi\Dto\ConsumerGroup::class],
    'plugin configs' => [fn (ApiClient $c) => $c->pluginConfigs(), 'plugin_configs', ['plugins' => ['cors' => ['allow_origins' => '*']]], Aybarsm\Apache\Apisix\AdminApi\Dto\PluginConfig::class],
    'ssls' => [fn (ApiClient $c) => $c->ssls(), 'ssls', ['sni' => 'a.test', 'cert' => 'C', 'key' => 'K'], Aybarsm\Apache\Apisix\AdminApi\Dto\Ssl::class],
    'global rules' => [fn (ApiClient $c) => $c->globalRules(), 'global_rules', ['plugins' => ['prometheus' => ['prefer_name' => true]]], Aybarsm\Apache\Apisix\AdminApi\Dto\GlobalRule::class],
]);

function envelopeFor(string $path, array $value): array
{
    return ['key' => "/apisix/{$path}/1", 'value' => ['id' => '1', ...$value], 'createdIndex' => 1, 'modifiedIndex' => 2];
}

it('exposes exactly the verbs its spec operations define', function (Closure $resource, string $path): void {
    $instance = $resource(fakeClient(fakeHttp()));
    $mapped = [];
    foreach ((new ReflectionClass($instance))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        foreach ($method->getAttributes(SpecOperation::class) as $attribute) {
            $mapped[] = $attribute->newInstance()->operationId;
        }
    }

    $expected = [];
    foreach (SpecExamples::spec()->implementable() as $op) {
        if ($op->path === '/apisix/admin/'.$path || str_starts_with($op->path, '/apisix/admin/'.$path.'/{id}')) {
            $expected[] = $op->id;
        }
    }
    sort($mapped);
    sort($expected);

    expect($instance)->toBeInstanceOf(AbstractResource::class)
        ->and($mapped)->toBe($expected);
})->with('crud resources');

it('sends each verb to the right method and path', function (Closure $resource, string $path, array $value, string $dto): void {
    $http = fakeHttp();
    $r = $resource(fakeClient($http));
    $calls = [
        'list' => fn () => $r->list(),
        'get' => fn () => $r->get('1'),
        'create' => fn () => $r->create($value),
        'put' => fn () => $r->put('1', $dto::fromArray($value)),
        'patch' => fn () => $r->patch('1', ['desc' => 'd']),
        'patchPath' => fn () => $r->patchPath('1', 'desc', 'd'),
        'delete' => fn () => $r->delete('1', force: true),
    ];
    $expected = [
        'list' => ['GET', "/apisix/admin/{$path}"],
        'get' => ['GET', "/apisix/admin/{$path}/1"],
        'create' => ['POST', "/apisix/admin/{$path}"],
        'put' => ['PUT', "/apisix/admin/{$path}/1"],
        'patch' => ['PATCH', "/apisix/admin/{$path}/1"],
        'patchPath' => ['PATCH', "/apisix/admin/{$path}/1/desc"],
        'delete' => ['DELETE', "/apisix/admin/{$path}/1?force=true"],
    ];

    foreach ($calls as $verb => $call) {
        if (! method_exists($r, $verb)) {
            continue;
        }
        match ($verb) {
            'list' => $http->json(200, ['total' => 1, 'list' => [envelopeFor($path, $value)]]),
            'delete' => $http->json(200, ['key' => "/apisix/{$path}/1", 'deleted' => '1']),
            default => $http->json($verb === 'put' || $verb === 'create' ? 201 : 200, envelopeFor($path, $value)),
        };

        $result = $call();

        expect([$http->last()->getMethod(), $http->lastTarget()])->toBe($expected[$verb]);
        match ($verb) {
            'list' => expect($result)->toBeInstanceOf(Page::class)->and($result->items[0]->value)->toBeInstanceOf($dto),
            'delete' => expect($result)->toBeInstanceOf(DeleteResult::class),
            default => expect($result)->toBeInstanceOf(Envelope::class)->and($result->value)->toBeInstanceOf($dto),
        };
    }

    $put = array_values(array_filter($http->requests, fn ($request) => $request->getMethod() === 'PUT'))[0] ?? null;
    if ($put !== null) {
        expect(json_decode((string) $put->getBody(), true))->toBe($value); // DTO body == input minus nulls
    }
})->with('crud resources');
