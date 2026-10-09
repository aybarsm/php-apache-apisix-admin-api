<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto\Route;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream;
use Aybarsm\Apache\Apisix\AdminApi\Enums\ResourceKind;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApiException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException;

it('serves resource and plugin schemas', function (): void {
    $schema = liveClient()->schema();

    expect($schema->resource(ResourceKind::Routes))->toHaveKey('properties')
        ->and($schema->plugin('key-auth'))->toHaveKey('properties');
});

it('validates resources without storing them', function (): void {
    $schema = liveClient()->schema();

    $schema->validate(ResourceKind::Routes, new Route(uri: '/apisix-php', upstream: new Upstream(nodes: ['127.0.0.1:1980' => 1])));

    expect(fn () => $schema->validate(ResourceKind::Routes, ['uri' => 123]))->toThrow(BadRequestException::class);
});

it('exposes standalone configuration when APISIX runs in API-driven standalone mode', function (): void {
    $standalone = liveClient()->standalone();

    try {
        $status = $standalone->status();
    } catch (ApiException $e) {
        $this->markTestSkipped(sprintf('Standalone mode is not enabled on this APISIX (%d %s).', $e->status, $e->errorMsg));
    }

    expect($status->config)->toBeNull()
        ->and($standalone->validate(['routes' => []]))->toBe([]);
});
