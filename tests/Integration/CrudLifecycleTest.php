<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\Dto\ConsumerGroup;
use Aybarsm\Apache\Apisix\AdminApi\Dto\GlobalRule;
use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginConfig;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Proto;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Route;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Service;
use Aybarsm\Apache\Apisix\AdminApi\Dto\StreamRoute;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Status;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;

const LIVE_LABELS = ['suite' => 'apisix-php'];

const LIVE_PROTO = 'syntax = "proto3"; package itest; service Echo { rpc Say (Msg) returns (Msg); } message Msg { string text = 1; }';

/**
 * name => [accessor, DTO factory (id => DTO), sub path, sub path value, sub path getter]
 */
dataset('live crud resources', [
    'routes' => [
        fn (ApiClient $c) => $c->routes(),
        fn (string $id) => new Route(name: $id, labels: LIVE_LABELS, uri: '/'.$id, methods: ['GET'], upstream: new Upstream(nodes: ['127.0.0.1:1980' => 1]), status: Status::Enabled),
        'status', 0, fn (Route $r) => $r->status,
    ],
    'services' => [
        fn (ApiClient $c) => $c->services(),
        fn (string $id) => new Service(name: $id, labels: LIVE_LABELS, upstream: new Upstream(nodes: ['127.0.0.1:1980' => 1])),
        'enable_websocket', true, fn (Service $s) => $s->enableWebsocket,
    ],
    'stream routes' => [
        fn (ApiClient $c) => $c->streamRoutes(),
        fn (string $id) => new StreamRoute(name: $id, labels: LIVE_LABELS, serverPort: 9100, upstream: new Upstream(nodes: ['127.0.0.1:1995' => 1])),
        null, null, null,
    ],
    'protos' => [
        fn (ApiClient $c) => $c->protos(),
        fn (string $id) => new Proto(content: LIVE_PROTO, name: $id, labels: LIVE_LABELS),
        null, null, null,
    ],
    'consumer groups' => [
        fn (ApiClient $c) => $c->consumerGroups(),
        fn (string $id) => new ConsumerGroup(plugins: ['limit-count' => ['count' => 100, 'time_window' => 60, 'rejected_code' => 503]], name: $id, labels: LIVE_LABELS),
        'plugins/limit-count/count', 50, fn (ConsumerGroup $g) => $g->plugins['limit-count']['count'] ?? null,
    ],
    'plugin configs' => [
        fn (ApiClient $c) => $c->pluginConfigs(),
        fn (string $id) => new PluginConfig(plugins: ['cors' => ['allow_origins' => '*']], name: $id, labels: LIVE_LABELS),
        'desc', 'via path', fn (PluginConfig $p) => $p->desc,
    ],
    'global rules' => [
        fn (ApiClient $c) => $c->globalRules(),
        fn (string $id) => new GlobalRule(plugins: ['response-rewrite' => ['headers' => ['set' => ['X-Apisix-Php' => $id]], 'vars' => [['http_x_apisix_php_test', '==', $id]]]]),
        'plugins/response-rewrite/headers/set/X-Apisix-Php', 'changed', fn (GlobalRule $g) => $g->plugins['response-rewrite']['headers']['set']['X-Apisix-Php'] ?? null,
    ],
]);

it('runs the full lifecycle against APISIX', function (Closure $resource, Closure $make, ?string $subPath, mixed $subValue, ?Closure $read): void {
    $resources = $resource(liveClient());
    $id = liveId();
    $dto = $make($id);

    try {
        $created = $resources->put($id, $dto);
    } catch (BadRequestException $e) {
        if (str_contains($e->errorMsg, 'stream mode is disabled')) {
            $this->markTestSkipped('APISIX stream proxy is disabled: '.$e->errorMsg);
        }
        throw $e;
    }

    try {
        expect($created->id())->toBe($id)
            ->and($created->value)->toBeInstanceOf($dto::class)
            ->and($created->value->createTime)->toBeInt();

        $named = property_exists($dto, 'name');
        $fetched = $resources->get($id);
        expect($fetched->id())->toBe($id);
        if ($named) {
            expect($fetched->value->name)->toBe($id)
                ->and($fetched->value->labels)->toBe(LIVE_LABELS)
                ->and(array_map(fn ($e) => $e->id(), $resources->list(new ListQuery(name: $id))->items))->toContain($id);
        }

        $lazy = [];
        foreach ($resources->lazy(new ListQuery(pageSize: 10, label: $named ? 'suite' : null)) as $envelope) {
            $lazy[] = $envelope->id();
        }
        expect($lazy)->toContain($id);

        if (method_exists($resources, 'patch') && property_exists($dto, 'desc')) {
            expect($resources->patch($id, ['desc' => 'patched'])->value->desc)->toBe('patched');
        }

        if ($subPath !== null && method_exists($resources, 'patchPath')) {
            $value = $read($resources->patchPath($id, $subPath, $subValue)->value);
            expect($value instanceof BackedEnum ? $value->value : $value)->toBe($subValue);
        }

        if (property_exists($dto, 'desc')) {
            expect($resources->put($id, $fetched->value->with(desc: 'replaced'))->value->desc)->toBe('replaced');
        }

        expect($resources->delete($id)->key)->toEndWith('/'.$id)
            ->and(fn () => $resources->get($id))->toThrow(NotFoundException::class);
    } finally {
        try {
            $resources->delete($id, force: true);
        } catch (NotFoundException) {
        }
    }
})->with('live crud resources');
