<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\AwsSecret;
use Aybarsm\Apache\Apisix\AdminApi\Dto\GcpSecret;
use Aybarsm\Apache\Apisix\AdminApi\Dto\VaultSecret;
use Aybarsm\Apache\Apisix\AdminApi\Enums\SecretType;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\HydrationException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

it('lists secrets of all types, hydrating each by the type in its key', function (): void {
    $example = SpecExamples::response('listSecrets')['1'];
    $example['list'][] = ['key' => '/apisix/secrets/aws/2', 'value' => ['id' => 'aws/2', 'access_key_id' => 'a', 'secret_access_key' => 's'], 'createdIndex' => 1, 'modifiedIndex' => 1];
    $example['total'] = 2;
    $http = fakeHttp()->json(200, $example);

    $page = fakeClient($http)->secrets()->list(new ListQuery(page: 1, pageSize: 10));

    expect($http->lastTarget())->toBe('/apisix/admin/secrets?page=1&page_size=10')
        ->and($page->total)->toBe(2)
        ->and($page->items[0]->value)->toBeInstanceOf(VaultSecret::class)
        ->and($page->items[0]->value->id)->toBe('vault/1')
        ->and($page->items[0]->value->createTime)->toBe(1684395392)
        ->and($page->items[1]->value)->toBeInstanceOf(AwsSecret::class)
        ->and(canonical($page->items[0]->toArray()))->toBe(canonical($example['list'][0]));
});

it('fails clearly on secrets of an unknown type', function (): void {
    $http = fakeHttp()->json(200, ['total' => 1, 'list' => [['key' => '/apisix/secrets/azure/1', 'value' => []]]]);

    expect(fn () => fakeClient($http)->secrets()->list())->toThrow(HydrationException::class, 'cannot infer the secret type');
});

it('iterates all secrets lazily', function (): void {
    $http = fakeHttp()->json(200, SpecExamples::response('listSecrets')['1']);

    expect(iterator_to_array(fakeClient($http)->secrets()->lazy(), false))->toHaveCount(1)
        ->and($http->lastTarget())->toBe('/apisix/admin/secrets?page=1&page_size=100');
});

it('scopes per-type operations to the secret manager', function (SecretType $type, string $class): void {
    $secrets = fakeClient(fakeHttp())->secrets()->of($type);

    expect($secrets->type)->toBe($type);
})->with([[SecretType::Vault, VaultSecret::class], [SecretType::Aws, AwsSecret::class], [SecretType::Gcp, GcpSecret::class]]);

it('runs vault secret CRUD against /secrets/vault', function (): void {
    $get = SpecExamples::response('getSecret')['1'];
    $http = fakeHttp()
        ->json(201, SpecExamples::response('createSecretById', '201')['1'])
        ->json(200, $get)
        ->json(200, SpecExamples::response('updateSecret')['1'])
        ->json(200, $get)
        ->json(200, ['total' => 1, 'list' => [$get]])
        ->json(200, SpecExamples::response('deleteSecret')['1']);
    $vault = fakeClient($http)->secrets()->vault();

    $created = $vault->put('1', new VaultSecret(uri: 'https://localhost/vault', prefix: '/apisix/kv', token: '343effad', namespace: 'apisix', id: 'vault/1', createTime: 1));
    expect([$http->last()->getMethod(), $http->lastTarget()])->toBe(['PUT', '/apisix/admin/secrets/vault/1'])
        ->and($http->lastJson())->toBe(['uri' => 'https://localhost/vault', 'prefix' => '/apisix/kv', 'token' => '343effad', 'namespace' => 'apisix'])
        ->and($created->value)->toBeInstanceOf(VaultSecret::class)
        ->and($created->value->namespace)->toBe('apisix');

    expect($vault->get('1')->value->token)->toBe('343effad');

    $vault->patch('1', ['token' => 'apisix']);
    expect([$http->last()->getMethod(), $http->lastJson()])->toBe(['PATCH', ['token' => 'apisix']]);

    $vault->patchPath('1', 'token', 'x');
    expect($http->lastTarget())->toBe('/apisix/admin/secrets/vault/1/token');

    expect($vault->list()->items[0]->value)->toBeInstanceOf(VaultSecret::class)
        ->and($http->lastTarget())->toBe('/apisix/admin/secrets/vault');

    $vault->delete('1', force: true);
    expect($http->lastTarget())->toBe('/apisix/admin/secrets/vault/1?force=true');
});

it('does not paginate the per-type list, which declares no parameters', function (): void {
    $http = fakeHttp()->json(200, ['total' => 0, 'list' => []]);
    $aws = fakeClient($http)->secrets()->aws();

    expect(iterator_to_array($aws->lazy(), false))->toBe([])
        ->and($http->lastTarget())->toBe('/apisix/admin/secrets/aws')
        ->and(fn () => $aws->list(new ListQuery(page: 1)))->toThrow(InvalidArgumentException::class, '"page"');
});

it('refuses to store a secret of another type', function (): void {
    $http = fakeHttp();

    expect(fn () => fakeClient($http)->secrets()->aws()->put('1', new VaultSecret(uri: 'u', prefix: 'p', token: 't')))
        ->toThrow(InvalidArgumentException::class, 'A aws secret resource cannot store')
        ->and($http->requests)->toBe([]);
});

it('round-trips the spec secret examples', function (string $operation, string $status): void {
    $example = SpecExamples::response($operation, $status)['1'];

    expect(canonical(Envelope::fromArray($example, VaultSecret::class)->toArray()))->toBe(canonical($example));
})->with([['getSecret', '200'], ['createSecretById', '201'], ['updateSecret', '200']]);
