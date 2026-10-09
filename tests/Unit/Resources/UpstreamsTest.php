<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Timeout;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream;
use Aybarsm\Apache\Apisix\AdminApi\Dto\UpstreamNodeItem;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamType;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

it('lists upstreams with filters and pagination', function (): void {
    $example = SpecExamples::response('listUpstreams')['1'];
    $http = fakeHttp()->json(200, $example);

    $page = fakeClient($http)->upstreams()->list(new ListQuery(page: 1, pageSize: 10, name: 'upstream-for-test', label: 'env:dev'));

    expect($http->last()->getMethod())->toBe('GET')
        ->and($http->lastTarget())->toBe('/apisix/admin/upstreams?page=1&page_size=10&name=upstream-for-test&label=env%3Adev')
        ->and($page->total)->toBe($example['total'])
        ->and($page->items[0]->value)->toBeInstanceOf(Upstream::class)
        ->and($page->items[0]->value->type)->toBe(UpstreamType::Roundrobin)
        ->and($page->items[0]->value->nodes)->toBe(['httpbin.org:443' => 1]);
});

it('rejects list filters the operation does not declare', function (): void {
    $http = fakeHttp();

    expect(fn () => fakeClient($http)->upstreams()->list(new ListQuery(uri: '/x')))->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
});

it('iterates lazily across pages and stops at the total', function (): void {
    $http = fakeHttp()->json(200, listResponse(25, 10))->json(200, listResponse(25, 10))->json(200, listResponse(25, 5));

    $items = iterator_to_array(fakeClient($http)->upstreams()->lazy(new ListQuery(pageSize: 10)), false);

    expect($items)->toHaveCount(25)
        ->and(array_map(fn ($r) => $r->getUri()->getQuery(), $http->requests))
        ->toBe(['page=1&page_size=10', 'page=2&page_size=10', 'page=3&page_size=10']);
});

it('does not request a page beyond an exact total', function (): void {
    $http = fakeHttp()->json(200, listResponse(20, 10))->json(200, listResponse(20, 10));

    expect(iterator_to_array(fakeClient($http)->upstreams()->lazy(new ListQuery(pageSize: 10)), false))->toHaveCount(20)
        ->and($http->requests)->toHaveCount(2);
});

it('stops on a short or empty page even if total is wrong', function (): void {
    $short = fakeHttp()->json(200, listResponse(999, 10))->json(200, listResponse(999, 3));
    $empty = fakeHttp()->json(200, listResponse(999, 10))->json(200, listResponse(999, 0));

    expect(iterator_to_array(fakeClient($short)->upstreams()->lazy(new ListQuery(pageSize: 10)), false))->toHaveCount(13)
        ->and(iterator_to_array(fakeClient($empty)->upstreams()->lazy(new ListQuery(pageSize: 10)), false))->toHaveCount(10)
        ->and($empty->requests)->toHaveCount(2);
});

it('starts lazily at the requested page with the default page size', function (): void {
    $http = fakeHttp()->json(200, listResponse(250, 50));

    $generator = fakeClient($http)->upstreams()->lazy(new ListQuery(page: 3));
    expect($http->requests)->toBe([]); // nothing fetched before iteration

    expect(iterator_to_array($generator, false))->toHaveCount(50)
        ->and($http->lastTarget())->toBe('/apisix/admin/upstreams?page=3&page_size=100');
});

it('gets an upstream by id', function (): void {
    $http = fakeHttp()->json(200, SpecExamples::response('getUpstream')['1']);

    $envelope = fakeClient($http)->upstreams()->get('1');

    expect($http->lastTarget())->toBe('/apisix/admin/upstreams/1')
        ->and($envelope->id())->toBe('1')
        ->and($envelope->value->timeout)->toEqual(new Timeout(15, 15, 15));
});

it('creates an upstream from a DTO without read-only fields', function (): void {
    $http = fakeHttp()->json(201, SpecExamples::response('createUpstream', '201')['1']);
    $upstream = new Upstream(
        nodes: [new UpstreamNodeItem(host: '127.0.0.1', weight: 1, port: 1980)],
        type: UpstreamType::Roundrobin,
        labels: [],
        createTime: 123,
    );

    $envelope = fakeClient($http)->upstreams()->create($upstream, ttl: 60);

    expect($http->last()->getMethod())->toBe('POST')
        ->and($http->lastTarget())->toBe('/apisix/admin/upstreams?ttl=60')
        ->and((string) $http->last()->getBody())->toBe('{"labels":{},"nodes":[{"host":"127.0.0.1","port":1980,"weight":1}],"type":"roundrobin"}')
        ->and($envelope->id())->toBe('00000000000000000128');
});

it('puts an upstream from an array', function (): void {
    $http = fakeHttp()->json(201, SpecExamples::response('createUpstreamById', '201')['1']);

    fakeClient($http)->upstreams()->put('1', ['nodes' => ['127.0.0.1:1980' => 1]]);

    expect($http->last()->getMethod())->toBe('PUT')
        ->and($http->lastTarget())->toBe('/apisix/admin/upstreams/1')
        ->and($http->lastJson())->toBe(['nodes' => ['127.0.0.1:1980' => 1]]);
});

it('patches with a merge-patch body including nulls', function (): void {
    $http = fakeHttp()->json(200, SpecExamples::response('updateUpstream')['1']);

    fakeClient($http)->upstreams()->patch('1', ['desc' => 'x', 'labels' => null]);

    expect($http->last()->getMethod())->toBe('PATCH')
        ->and((string) $http->last()->getBody())->toBe('{"desc":"x","labels":null}');
});

it('rejects empty patches', function (): void {
    expect(fn () => fakeClient(fakeHttp())->upstreams()->patch('1', []))->toThrow(InvalidArgumentException::class);
});

it('patches a sub path with any JSON value', function (): void {
    $http = fakeHttp()->json(200, SpecExamples::response('updateUpstream')['1']);

    fakeClient($http)->upstreams()->patchPath('1', 'nodes', ['127.0.0.1:1981' => 1]);

    expect($http->lastTarget())->toBe('/apisix/admin/upstreams/1/nodes')
        ->and($http->lastJson())->toBe(['127.0.0.1:1981' => 1]);
});

it('rejects malformed sub paths', function (string $path): void {
    expect(fn () => fakeClient(fakeHttp())->upstreams()->patchPath('1', $path, 1))->toThrow(InvalidArgumentException::class);
})->with(['', '/', 'a//b']);

it('deletes with optional force', function (): void {
    $http = fakeHttp()->json(200, SpecExamples::response('deleteUpstream')['1'])->json(200, ['key' => '/apisix/upstreams/2', 'deleted' => '1']);
    $upstreams = fakeClient($http)->upstreams();

    expect($upstreams->delete('1')->key)->toBe('/apisix/upstreams/1')
        ->and($http->lastTarget())->toBe('/apisix/admin/upstreams/1');

    $upstreams->delete(2, force: true);
    expect($http->lastTarget())->toBe('/apisix/admin/upstreams/2?force=true');
});

it('validates ids and ttl before sending', function (): void {
    $http = fakeHttp();
    $upstreams = fakeClient($http)->upstreams();

    expect(fn () => $upstreams->get('bad/id'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $upstreams->put('1', [], ttl: 0))->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
});

it('surfaces 404s as NotFoundException', function (): void {
    $http = fakeHttp()->json(404, ['error_msg' => 'Key not found']);

    expect(fn () => fakeClient($http)->upstreams()->get('missing'))->toThrow(NotFoundException::class, 'Key not found');
});
