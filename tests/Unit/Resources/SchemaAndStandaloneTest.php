<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\ConfigValidationError;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Route;
use Aybarsm\Apache\Apisix\AdminApi\Enums\ResourceKind;
use Aybarsm\Apache\Apisix\AdminApi\Enums\StandaloneUpdate;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnexpectedResponseException;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;
use GuzzleHttp\Psr7\Response;

describe('schema', function (): void {
    it('fetches resource and plugin schemas', function (): void {
        $http = fakeHttp()->json(200, ['type' => 'object'])->json(200, ['properties' => []]);
        $schema = fakeClient($http)->schema();

        expect($schema->resource(ResourceKind::StreamRoutes))->toBe(['type' => 'object'])
            ->and($http->lastTarget())->toBe('/apisix/admin/schema/stream_routes')
            ->and($schema->plugin('key-auth'))->toBe(['properties' => []])
            ->and($http->lastTarget())->toBe('/apisix/admin/schema/plugins/key-auth');
    });

    it('validates a resource and throws on invalid configuration', function (): void {
        $http = fakeHttp()->push(new Response(200))->json(400, ['error_msg' => 'invalid configuration: property "uri" is required']);
        $schema = fakeClient($http)->schema();

        $schema->validate(ResourceKind::Routes, new Route(uri: '/x', createTime: 1));
        expect([$http->last()->getMethod(), $http->lastTarget()])->toBe(['POST', '/apisix/admin/schema/validate/routes'])
            ->and($http->lastJson())->toBe(['uri' => '/x']);

        expect(fn () => $schema->validate(ResourceKind::Routes, []))->toThrow(BadRequestException::class, 'property "uri" is required');
    });
});

describe('standalone', function (): void {
    it('reports status and snapshot metadata from headers', function (): void {
        $http = fakeHttp()
            ->push(new Response(200, ['X-Digest' => 'abc', 'X-Last-Modified' => '1700000000']))
            ->json(200, ['routes' => [['id' => 'r1', 'uri' => '/x']]], ['X-Digest' => 'abc']);
        $standalone = fakeClient($http)->standalone();

        $status = $standalone->status();
        expect([$http->last()->getMethod(), $http->lastTarget()])->toBe(['HEAD', '/apisix/admin/configs'])
            ->and($status->digest)->toBe('abc')
            ->and($status->lastModified)->toBe(1700000000)
            ->and($status->config)->toBeNull();

        $snapshot = $standalone->get();
        expect($snapshot->config)->toBe(['routes' => [['id' => 'r1', 'uri' => '/x']]])
            ->and($snapshot->lastModified)->toBeNull();
    });

    it('puts a snapshot with digest and wait, mapping the success status', function (int $status, StandaloneUpdate $expected): void {
        $http = fakeHttp()->push(new Response($status));

        $result = fakeClient($http)->standalone()->put(['routes' => [new Route(id: 'r1', uri: '/x')], 'routes_conf_version' => 2], 'v2', wait: 5000);

        expect($result)->toBe($expected)
            ->and([$http->last()->getMethod(), $http->lastTarget()])->toBe(['PUT', '/apisix/admin/configs?wait=5000'])
            ->and($http->last()->getHeaderLine('X-Digest'))->toBe('v2')
            ->and($http->lastJson())->toBe(['routes' => [['id' => 'r1', 'uri' => '/x']], 'routes_conf_version' => 2]);
    })->with([[200, StandaloneUpdate::Synced], [202, StandaloneUpdate::Accepted], [204, StandaloneUpdate::Unchanged]]);

    it('rejects empty digests and unexpected success statuses', function (): void {
        expect(fn () => fakeClient(fakeHttp())->standalone()->put([], ' '))->toThrow(InvalidArgumentException::class)
            ->and(fn () => fakeClient(fakeHttp()->push(new Response(201)))->standalone()->put([], 'd'))->toThrow(UnexpectedResponseException::class);
    });

    it('returns no errors for a valid document', function (): void {
        $http = fakeHttp()->json(200, []);

        expect(fakeClient($http)->standalone()->validate(['routes' => []]))->toBe([])
            ->and([$http->last()->getMethod(), $http->lastTarget()])->toBe(['POST', '/apisix/admin/configs/validate']);
    });

    it('returns typed errors for an invalid document', function (): void {
        $example = SpecExamples::spec()->document['paths']['/apisix/admin/configs/validate']['post']['responses']['400']['content']['application/json']['examples']['configurationErrors']['value'];
        $http = fakeHttp()->json(400, $example);

        $errors = fakeClient($http)->standalone()->validate(['routes' => [['id' => 'route-a', 'uri' => 1]]]);

        expect($errors)->toHaveCount(count($example['errors']))
            ->each->toBeInstanceOf(ConfigValidationError::class)
            ->and($errors[0]->resourceType)->toBe('routes')
            ->and($errors[0]->resourceId)->toBe('route-a')
            ->and($errors[0]->index)->toBe(0)
            ->and(canonical(array_map(fn ($e) => $e->toArray(), $errors)))->toBe(canonical($example['errors']));
    });

    it('rethrows 400s that carry no validation errors', function (): void {
        $http = fakeHttp()->json(400, ['error_msg' => 'invalid request body: empty request body']);

        expect(fn () => fakeClient($http)->standalone()->validate([]))->toThrow(BadRequestException::class, 'empty request body');
    });
});
