<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\ClientConfig;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnauthorizedException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\Env;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

it('pings the live Admin API without a key', function (): void {
    liveClient(requireKey: false);

    expect(ApiClient::create(Env::adminUrl(), '')->ping())->toBeTrue();
});

it('reports unreachable hosts as down', function (): void {
    expect(ApiClient::create('http://127.0.0.1:1', '')->ping())->toBeFalse();
});

it('rejects a wrong admin key with UnauthorizedException', function (): void {
    liveClient(requireKey: false);
    $factory = new HttpFactory();
    $transport = new Transport(new ClientConfig(Env::adminUrl(), 'definitely-wrong-key'), new Client(['http_errors' => false]), $factory, $factory);

    try {
        $transport->send(HttpMethod::Get, ['routes']);
        $this->fail('Expected UnauthorizedException');
    } catch (UnauthorizedException $e) {
        expect($e->status)->toBe(401)
            ->and($e->errorMsg)->toBe('failed to check token')
            ->and($e->description)->toBe('wrong apikey')
            ->and($e->getMessage())->not->toContain('definitely-wrong-key');
    }
});
