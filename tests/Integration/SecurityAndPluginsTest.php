<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginAttributes;
use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginMetadata;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Ssl;
use Aybarsm\Apache\Apisix\AdminApi\Dto\VaultSecret;
use Aybarsm\Apache\Apisix\AdminApi\Enums\SslType;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Status;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;

it('manages SSL certificates without ever reading the private key back', function (): void {
    $ssls = liveClient()->ssls();
    $id = liveId();
    $sni = $id.'.apisix-php.test';
    $pair = selfSignedCertificate($sni);

    try {
        $created = $ssls->put($id, new Ssl(labels: ['suite' => 'apisix-php'], type: SslType::Server, sni: $sni, cert: $pair['cert'], key: $pair['key']));
        expect($created->id())->toBe($id);

        $fetched = $ssls->get($id);
        expect($fetched->value->sni)->toBe($sni)
            ->and($fetched->value->status)->toBe(Status::Enabled)
            ->and($fetched->value->key)->toBeNull(); // APISIX strips keys on read

        expect(array_map(fn ($e) => $e->id(), $ssls->list(new ListQuery(label: 'suite:apisix-php'))->items))->toContain($id)
            ->and($ssls->patch($id, ['desc' => 'patched'])->value->desc)->toBe('patched')
            ->and($ssls->patchPath($id, 'status', 0)->value->status)->toBe(Status::Disabled)
            ->and($ssls->delete($id)->key)->toEndWith('/ssls/'.$id)
            ->and(fn () => $ssls->get($id))->toThrow(NotFoundException::class);
    } finally {
        try {
            $ssls->delete($id, force: true);
        } catch (NotFoundException) {
        }
    }
});

it('stores vault secret configurations', function (): void {
    $secrets = liveClient()->secrets();
    $vault = $secrets->vault();
    $id = liveId();

    try {
        $created = $vault->put($id, new VaultSecret(uri: 'http://127.0.0.1:8200', prefix: '/kv/apisix-php', token: 'token-'.$id));
        expect($created->value)->toBeInstanceOf(VaultSecret::class)
            ->and($created->value->id)->toBe('vault/'.$id);

        expect($vault->get($id)->value->token)->toBe('token-'.$id)
            ->and($vault->patch($id, ['namespace' => 'it'])->value->namespace)->toBe('it')
            ->and($vault->patchPath($id, 'token', 'rotated')->value->token)->toBe('rotated')
            ->and(array_map(fn ($e) => $e->id(), $vault->list()->items))->toContain($id);

        $all = [];
        foreach ($secrets->lazy(new ListQuery(pageSize: 10)) as $envelope) {
            $all[] = $envelope->key;
        }
        expect($all)->toContain('/apisix/secrets/vault/'.$id);

        expect($vault->delete($id)->key)->toEndWith('/secrets/vault/'.$id);
    } finally {
        try {
            $vault->delete($id, force: true);
        } catch (NotFoundException) {
        }
    }
});

it('manages plugin metadata', function (): void {
    $metadata = liveClient()->pluginMetadata();
    $format = ['host' => '$host', 'apisix_php' => liveId()];

    try {
        $stored = $metadata->put('http-logger', new PluginMetadata(['log_format' => $format]));
        expect($stored->id())->toBe('http-logger')
            ->and($metadata->get('http-logger')->value->config['log_format'] ?? null)->toBe($format);

        $names = [];
        foreach ($metadata->lazy(new ListQuery(pageSize: 10)) as $envelope) {
            $names[] = $envelope->id();
        }
        expect($names)->toContain('http-logger')
            ->and($metadata->delete('http-logger')->key)->toEndWith('/plugin_metadata/http-logger');
    } finally {
        try {
            $metadata->delete('http-logger', force: true);
        } catch (NotFoundException) {
        }
    }
});

it('reads plugin names, attributes and schemas', function (): void {
    $plugins = liveClient()->plugins();

    $names = $plugins->names();
    expect($names)->toContain('key-auth', 'limit-count');

    $attributes = $plugins->attributes();
    expect($attributes['key-auth'] ?? null)->toBeInstanceOf(PluginAttributes::class)
        ->and($attributes['key-auth']->priority)->toBeInt()
        ->and($plugins->schema('limit-count')['properties'] ?? null)->toBeArray();
});

it('reloads plugins', function (): void {
    expect(liveClient()->plugins()->reload())->toContain('done');
});
