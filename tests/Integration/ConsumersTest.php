<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Consumer;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Credential;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;

it('manages consumers and their credentials', function (): void {
    $consumers = liveClient()->consumers();
    $username = str_replace('-', '_', liveId('it'));

    try {
        $created = $consumers->put(new Consumer(username: $username, desc: 'apisix-php', labels: ['suite' => 'apisix-php']));
        expect($created->id())->toBe($username)
            ->and($created->value->username)->toBe($username);

        expect($consumers->get($username)->value->desc)->toBe('apisix-php');

        $listed = [];
        foreach ($consumers->lazy(new ListQuery(label: 'suite', pageSize: 10)) as $envelope) {
            $listed[] = $envelope->id();
        }
        expect($listed)->toContain($username);

        $credentials = $consumers->credentials($username);
        $credential = $credentials->put('cred-1', new Credential(name: 'primary', plugins: ['key-auth' => ['key' => 'key-'.$username]]));
        expect($credential->id())->toBe('cred-1');

        // getCredential returns a single envelope (spec declares a list; see overrides)
        $fetched = $credentials->get('cred-1');
        expect($fetched->value)->toBeInstanceOf(Credential::class)
            ->and($fetched->value->name)->toBe('primary');

        expect($credentials->list()->total)->toBe(1)
            ->and($credentials->delete('cred-1')->key)->toEndWith('/credentials/cred-1')
            ->and($consumers->delete($username)->key)->toEndWith('/consumers/'.$username)
            ->and(fn () => $consumers->get($username))->toThrow(NotFoundException::class);
    } finally {
        try {
            $consumers->delete($username, force: true);
        } catch (NotFoundException) {
        }
    }
});
