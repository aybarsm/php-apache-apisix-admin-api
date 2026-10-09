<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Consumer;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Credential;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

function consumerEnvelope(string $username = 'jack'): array
{
    return ['key' => "/apisix/consumers/{$username}", 'value' => ['username' => $username, 'plugins' => ['key-auth' => ['key' => 'k']]], 'createdIndex' => 1, 'modifiedIndex' => 1];
}

it('puts consumers on the collection with the username in the body', function (): void {
    $http = fakeHttp()->json(201, consumerEnvelope());

    $envelope = fakeClient($http)->consumers()->put(new Consumer(username: 'jack', plugins: ['key-auth' => ['key' => 'k']], createTime: 1), ttl: 30);

    expect($http->last()->getMethod())->toBe('PUT')
        ->and($http->lastTarget())->toBe('/apisix/admin/consumers?ttl=30')
        ->and($http->lastJson())->toBe(['username' => 'jack', 'plugins' => ['key-auth' => ['key' => 'k']]])
        ->and($envelope->id())->toBe('jack')
        ->and($envelope->value->username)->toBe('jack');
});

it('gets, lists and deletes consumers by username', function (): void {
    $http = fakeHttp()
        ->json(200, consumerEnvelope())
        ->json(200, ['total' => 1, 'list' => [consumerEnvelope()]])
        ->json(200, ['key' => '/apisix/consumers/jack', 'deleted' => '1']);
    $consumers = fakeClient($http)->consumers();

    expect($consumers->get('jack')->value)->toBeInstanceOf(Consumer::class)
        ->and($http->lastTarget())->toBe('/apisix/admin/consumers/jack');

    $consumers->list(new ListQuery(label: 'team:a', page: 1, pageSize: 10));
    expect($http->lastTarget())->toBe('/apisix/admin/consumers?page=1&page_size=10&label=team%3Aa');

    $consumers->delete('jack', force: true);
    expect($http->lastTarget())->toBe('/apisix/admin/consumers/jack?force=true');
});

it('rejects filters consumers do not support and invalid usernames', function (): void {
    $http = fakeHttp();
    $consumers = fakeClient($http)->consumers();

    expect(fn () => $consumers->list(new ListQuery(name: 'x')))->toThrow(InvalidArgumentException::class, '"name"')
        ->and(fn () => $consumers->get('jack.doe'))->toThrow(InvalidArgumentException::class, 'username')
        ->and(fn () => $consumers->credentials('a/b'))->toThrow(InvalidArgumentException::class)
        ->and($http->requests)->toBe([]);
});

it('manages credentials nested under a consumer', function (): void {
    $credential = ['key' => '/apisix/consumers/jack/credentials/c1', 'value' => ['id' => 'c1', 'plugins' => ['key-auth' => ['key' => 'k']]], 'createdIndex' => 1, 'modifiedIndex' => 1];
    $http = fakeHttp()
        ->json(201, $credential)
        ->json(200, $credential)
        ->json(200, ['total' => 1, 'list' => [$credential]])
        ->json(200, ['key' => '/apisix/consumers/jack/credentials/c1', 'deleted' => '1']);
    $credentials = fakeClient($http)->consumers()->credentials('jack');

    expect($credentials->username)->toBe('jack')
        ->and($credentials->put('c1', new Credential(plugins: ['key-auth' => ['key' => 'k']]))->id())->toBe('c1')
        ->and($http->last()->getMethod())->toBe('PUT')
        ->and($http->lastTarget())->toBe('/apisix/admin/consumers/jack/credentials/c1');

    expect($credentials->get('c1')->value)->toBeInstanceOf(Credential::class)
        ->and($credentials->list(new ListQuery(name: 'n'))->total)->toBe(1)
        ->and($http->lastTarget())->toBe('/apisix/admin/consumers/jack/credentials?name=n');

    $credentials->delete('c1', force: true);
    expect($http->lastTarget())->toBe('/apisix/admin/consumers/jack/credentials/c1?force=true');
});

it('covers exactly the consumer and credential operations of the spec', function (): void {
    expect(array_keys(array_filter(
        SpecExamples::spec()->implementable(),
        fn ($op) => in_array($op->tag(), ['Consumers', 'Credentials'], true),
    )))->toEqualCanonicalizing([
        'createConsumer', 'deleteConsumer', 'getConsumer', 'listConsumers',
        'createCredentialById', 'deleteCredential', 'getCredential', 'listCredentials',
    ]);
});
