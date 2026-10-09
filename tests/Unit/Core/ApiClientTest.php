<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('pings without authentication', function (): void {
    $http = fakeHttp()->push(new Response(200));

    expect(fakeClient($http)->ping())->toBeTrue()
        ->and($http->last()->getMethod())->toBe('HEAD')
        ->and($http->lastTarget())->toBe('/apisix/admin')
        ->and($http->last()->hasHeader('X-API-KEY'))->toBeFalse();
});

it('reports ping failures as false', function (): void {
    $http = fakeHttp()->push(new Response(503))->push(new ConnectException('down', new Request('HEAD', '/')));
    $client = fakeClient($http);

    expect($client->ping())->toBeFalse()
        ->and($client->ping())->toBeFalse();
});
