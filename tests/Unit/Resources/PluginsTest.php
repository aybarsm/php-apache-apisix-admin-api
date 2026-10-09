<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginAttributes;
use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginMetadata;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Subsystem;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\HydrationException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;
use GuzzleHttp\Psr7\Response;

describe('plugins', function (): void {
    it('lists plugin names per subsystem', function (): void {
        $http = fakeHttp()->json(200, SpecExamples::response('listPluginNames')['1']);

        $names = fakeClient($http)->plugins()->names(Subsystem::Stream);

        expect($names)->toContain('cors')
            ->and($http->lastTarget())->toBe('/apisix/admin/plugins/list?subsystem=stream');
    });

    it('rejects non-list plugin name responses', function (): void {
        expect(fn () => fakeClient(fakeHttp()->json(200, ['a' => 1]))->plugins()->names())->toThrow(HydrationException::class);
    });

    it('returns typed attributes keyed by plugin name', function (): void {
        $example = SpecExamples::response('listPluginAttributes')['1'];
        $http = fakeHttp()->json(200, $example);

        $attributes = fakeClient($http)->plugins()->attributes();

        expect($http->lastTarget())->toBe('/apisix/admin/plugins?all=true&subsystem=http')
            ->and($attributes)->toHaveKey('limit-conn')
            ->and($attributes['limit-conn'])->toBeInstanceOf(PluginAttributes::class)
            ->and($attributes['limit-conn']->priority)->toBe(1003)
            ->and($attributes['limit-conn']->version)->toBe(0.1)
            ->and($attributes['limit-conn']->schema['type'] ?? null)->toBe('object');
        foreach ($attributes as $name => $attribute) {
            expect(canonical($attribute->toArray()))->toBe(canonical($example[$name]));
        }
    });

    it('fetches a plugin schema', function (): void {
        $http = fakeHttp()->json(200, SpecExamples::response('getPluginSchema')['1']);

        $schema = fakeClient($http)->plugins()->schema('limit-count');

        expect($schema['required'])->toBe(['count', 'time_window'])
            ->and($http->lastTarget())->toBe('/apisix/admin/plugins/limit-count?subsystem=http')
            ->and(fn () => fakeClient(fakeHttp())->plugins()->schema('a/b'))->toThrow(InvalidArgumentException::class);
    });

    it('reloads plugins and returns the plain-text answer', function (): void {
        $http = fakeHttp()->push(new Response(200, ['Content-Type' => 'text/plain'], 'done'));

        expect(fakeClient($http)->plugins()->reload())->toBe('done')
            ->and([$http->last()->getMethod(), $http->lastTarget()])->toBe(['PUT', '/apisix/admin/plugins/reload']);
    });
});

describe('plugin metadata', function (): void {
    it('gets and lists metadata as free-form config', function (): void {
        $get = SpecExamples::response('getPluginMetadata')['1'];
        $http = fakeHttp()->json(200, $get)->json(200, SpecExamples::response('listPluginMetadata')['1']);
        $metadata = fakeClient($http)->pluginMetadata();

        $envelope = $metadata->get('syslog');
        expect($http->lastTarget())->toBe('/apisix/admin/plugin_metadata/syslog')
            ->and($envelope->id())->toBe('syslog')
            ->and($envelope->value->config['log_format']['host'] ?? null)->toBe('$host')
            ->and(canonical($envelope->toArray()))->toBe(canonical($get));

        $page = $metadata->list(new ListQuery(page: 1, pageSize: 10));
        expect($page->items[0]->value)->toBeInstanceOf(PluginMetadata::class)
            ->and($http->lastTarget())->toBe('/apisix/admin/plugin_metadata?page=1&page_size=10');
    });

    it('puts metadata without the injected id and deletes it', function (): void {
        $http = fakeHttp()
            ->json(201, SpecExamples::response('createPluginMetadata', '201')['1'])
            ->json(200, SpecExamples::response('deletePluginMetadata')['1']);
        $metadata = fakeClient($http)->pluginMetadata();

        $metadata->put('syslog', new PluginMetadata(['id' => 'syslog', 'log_format' => ['host' => '$host']]), ttl: 10);
        expect([$http->last()->getMethod(), $http->lastTarget()])->toBe(['PUT', '/apisix/admin/plugin_metadata/syslog?ttl=10'])
            ->and($http->lastJson())->toBe(['log_format' => ['host' => '$host']]);

        $metadata->delete('syslog');
        expect([$http->last()->getMethod(), $http->lastTarget()])->toBe(['DELETE', '/apisix/admin/plugin_metadata/syslog']);
    });

    it('validates plugin names', function (): void {
        expect(fn () => fakeClient(fakeHttp())->pluginMetadata()->get(''))->toThrow(InvalidArgumentException::class);
    });
});
