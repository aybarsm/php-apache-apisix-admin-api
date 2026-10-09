<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Timeout;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamType;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;

beforeEach(function (): void {
    $this->client = liveClient();
    $this->ids = [];
});

afterEach(function (): void {
    foreach ($this->ids ?? [] as $id) {
        try {
            $this->client->upstreams()->delete($id, force: true);
        } catch (NotFoundException) {
        }
    }
});

it('runs the full upstream lifecycle', function (): void {
    $upstreams = $this->client->upstreams();
    $id = $this->ids[] = liveId();
    $name = 'apisix-php-'.$id;

    $created = $upstreams->put($id, new Upstream(
        name: $name,
        labels: ['suite' => 'apisix-php'],
        nodes: ['127.0.0.1:1980' => 1],
        type: UpstreamType::Roundrobin,
        timeout: new Timeout(5, 5, 5),
    ));
    expect($created->id())->toBe($id)
        ->and($created->value->createTime)->toBeInt();

    $fetched = $upstreams->get($id);
    expect($fetched->value->name)->toBe($name)
        ->and($fetched->value->nodes)->toBe(['127.0.0.1:1980' => 1])
        ->and($fetched->value->timeout)->toEqual(new Timeout(5, 5, 5));

    $page = $upstreams->list(new ListQuery(name: $name));
    expect(array_map(fn ($e) => $e->id(), $page->items))->toContain($id);

    $lazyIds = [];
    foreach ($upstreams->lazy(new ListQuery(pageSize: 10, label: 'suite:apisix-php')) as $envelope) {
        $lazyIds[] = $envelope->id();
    }
    expect($lazyIds)->toContain($id);

    $patched = $upstreams->patch($id, ['desc' => 'patched', 'labels' => ['suite' => 'apisix-php', 'stage' => 'patch']]);
    expect($patched->value->desc)->toBe('patched')
        ->and($patched->value->labels)->toBe(['suite' => 'apisix-php', 'stage' => 'patch']);

    $pathed = $upstreams->patchPath($id, 'retries', 2);
    expect($pathed->value->retries)->toBe(2)
        ->and($pathed->value->desc)->toBe('patched');

    $replaced = $upstreams->put($id, $fetched->value->with(desc: 'replaced'));
    expect($replaced->value->desc)->toBe('replaced')
        ->and($replaced->value->retries)->toBeNull();

    $deleted = $upstreams->delete($id);
    expect($deleted->key)->toEndWith('/upstreams/'.$id);

    expect(fn () => $upstreams->get($id))->toThrow(NotFoundException::class);
});

it('creates an upstream with a server-generated id', function (): void {
    $created = $this->client->upstreams()->create(['nodes' => [['host' => '127.0.0.1', 'port' => 1980, 'weight' => 1]], 'labels' => ['suite' => 'apisix-php']]);
    $this->ids[] = $created->id();

    expect($created->id())->not->toBe('')
        ->and($created->value->nodes)->toHaveCount(1);
});
