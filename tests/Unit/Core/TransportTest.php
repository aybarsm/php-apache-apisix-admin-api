<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApacheApisixApiException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApiException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ConflictException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ConnectionException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ForbiddenException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\NotFoundException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\RequestException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ServerException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnauthorizedException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnexpectedResponseException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\UnexpectedStatusException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\JsonBody;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

it('prefixes paths and URL-encodes each segment', function (): void {
    $http = fakeHttp()->json(200, ['ok' => true]);

    fakeTransport($http)->send(HttpMethod::Get, ['routes', 'a b/c', 7]);

    expect((string) $http->last()->getUri())->toBe(TEST_BASE_URI.'/apisix/admin/routes/a%20b%2Fc/7');
});

it('builds RFC 3986 query strings, dropping nulls and stringifying booleans', function (): void {
    $http = fakeHttp()->json(200, []);

    fakeTransport($http)->send(HttpMethod::Get, ['routes'], ['page' => 2, 'name' => 'a b', 'uri' => null, 'force' => true]);

    expect($http->lastTarget())->toBe('/apisix/admin/routes?page=2&name=a%20b&force=true');
});

it('sends the admin key header unless disabled', function (): void {
    $http = fakeHttp()->json(200, [])->json(200, []);
    $transport = fakeTransport($http);

    $transport->send(HttpMethod::Get, ['routes']);
    expect($http->last()->getHeaderLine('X-API-KEY'))->toBe(TEST_API_KEY);

    $transport->send(HttpMethod::Head, authenticate: false);
    expect($http->last()->hasHeader('X-API-KEY'))->toBeFalse();
});

it('omits the key header when no key is configured', function (): void {
    $http = fakeHttp()->json(200, []);

    fakeTransport($http, '')->send(HttpMethod::Get, ['routes']);

    expect($http->last()->hasHeader('X-API-KEY'))->toBeFalse();
});

it('sends accept and user-agent headers', function (): void {
    $http = fakeHttp()->json(200, []);

    fakeTransport($http)->send(HttpMethod::Get, headers: ['X-Digest' => 'abc']);

    expect($http->last()->getHeaderLine('Accept'))->toBe('application/json')
        ->and($http->last()->getHeaderLine('User-Agent'))->toContain('apache-apisix-admin-api')
        ->and($http->last()->getHeaderLine('X-Digest'))->toBe('abc');
});

it('encodes JSON bodies keeping slashes, unicode, zero fractions and empty objects', function (): void {
    $http = fakeHttp()->json(201, []);

    fakeTransport($http)->send(HttpMethod::Put, ['routes', '1'], body: new JsonBody([
        'uri' => '/a/b',
        'desc' => 'çay',
        'weight' => 1.0,
        'plugins' => new stdClass(),
    ]));

    expect((string) $http->last()->getBody())->toBe('{"uri":"/a/b","desc":"çay","weight":1.0,"plugins":{}}')
        ->and($http->last()->getHeaderLine('Content-Type'))->toBe('application/json');
});

it('can send a JSON null body', function (): void {
    $http = fakeHttp()->json(200, []);

    fakeTransport($http)->send(HttpMethod::Patch, ['routes', '1', 'desc'], body: new JsonBody(null));

    expect((string) $http->last()->getBody())->toBe('null');
});

it('sends no body when none is given', function (): void {
    $http = fakeHttp()->json(200, []);

    fakeTransport($http)->send(HttpMethod::Get, ['routes']);

    expect((string) $http->last()->getBody())->toBe('')
        ->and($http->last()->hasHeader('Content-Type'))->toBeFalse();
});

it('rejects non-encodable bodies and empty segments', function (): void {
    $transport = fakeTransport(fakeHttp());

    expect(fn () => $transport->send(HttpMethod::Put, ['x'], body: new JsonBody(NAN)))->toThrow(InvalidArgumentException::class, 'not JSON-encodable')
        ->and(fn () => $transport->send(HttpMethod::Get, ['routes', '']))->toThrow(InvalidArgumentException::class, 'must not be empty');
});

it('decodes JSON, plain-text and empty bodies', function (): void {
    $http = fakeHttp()
        ->json(200, ['total' => 0, 'list' => []])
        ->push(new Response(200, ['Content-Type' => 'text/plain'], 'done'))
        ->push(new Response(200));
    $transport = fakeTransport($http);

    expect($transport->send(HttpMethod::Get)->object())->toBe(['total' => 0, 'list' => []])
        ->and($transport->send(HttpMethod::Put)->body)->toBe('done')
        ->and($transport->send(HttpMethod::Head)->body)->toBeNull();
});

it('decodes JSON bodies even without a JSON content type', function (): void {
    $http = fakeHttp()->push(new Response(200, ['Content-Type' => 'text/plain'], '{"a":1}'));

    expect(fakeTransport($http)->send(HttpMethod::Get)->body)->toBe(['a' => 1]);
});

it('keeps undecodable JSON bodies raw and fails only when an object is required', function (): void {
    $http = fakeHttp()
        ->push(new Response(200, ['Content-Type' => 'application/json'], '{oops'))
        ->push(new Response(200, ['Content-Type' => 'application/json'], 'done'));
    $transport = fakeTransport($http);

    $broken = $transport->send(HttpMethod::Get, ['routes']);
    expect($broken->body)->toBe('{oops')
        ->and(fn () => $broken->object())->toThrow(UnexpectedResponseException::class, 'GET /apisix/admin/routes: 200 invalid JSON body')
        ->and($transport->send(HttpMethod::Put, ['plugins', 'reload'])->body)->toBe('done');
});

it('maps error statuses onto exceptions', function (int $status, string $class): void {
    $http = fakeHttp()->json($status, ['error_msg' => 'boom', 'description' => 'why']);

    try {
        fakeTransport($http)->send(HttpMethod::Delete, ['routes', '1']);
        $this->fail('Expected an exception');
    } catch (ApiException $e) {
        expect($e)->toBeInstanceOf($class)
            ->toBeInstanceOf(ApacheApisixApiException::class)
            ->and($e->status)->toBe($status)
            ->and($e->errorMsg)->toBe('boom')
            ->and($e->description)->toBe('why')
            ->and($e->method)->toBe('DELETE')
            ->and($e->uri)->toBe('/apisix/admin/routes/1')
            ->and($e->getCode())->toBe($status)
            ->and($e->getMessage())->toBe("DELETE /apisix/admin/routes/1: {$status} boom (why)");
    }
})->with([
    [400, BadRequestException::class],
    [401, UnauthorizedException::class],
    [403, ForbiddenException::class],
    [404, NotFoundException::class],
    [409, ConflictException::class],
    [500, ServerException::class],
    [503, ServerException::class],
    [418, UnexpectedStatusException::class],
]);

it('falls back to the raw body when error_msg is missing', function (): void {
    $http = fakeHttp()->push(new Response(502, ['Content-Type' => 'text/html'], '<html>Bad Gateway</html>'))->push(new Response(500));
    $transport = fakeTransport($http);

    expect(fn () => $transport->send(HttpMethod::Get))->toThrow(ServerException::class, '502 <html>Bad Gateway</html>')
        ->and(fn () => $transport->send(HttpMethod::Get))->toThrow(ServerException::class, '500 empty response body');
});

it('never leaks the admin key in exception messages', function (): void {
    $http = fakeHttp()->json(401, ['error_msg' => 'failed to check token', 'description' => 'wrong apikey']);

    try {
        fakeTransport($http)->send(HttpMethod::Get, ['routes']);
    } catch (UnauthorizedException $e) {
        expect($e->getMessage())->not->toContain(TEST_API_KEY)
            ->and($e->uri)->not->toContain(TEST_API_KEY);
    }
});

it('maps PSR-18 failures onto transport exceptions', function (): void {
    $request = new Request('GET', '/');
    $http = fakeHttp()
        ->push(new ConnectException('Connection refused', $request))
        ->push(new TransferException('broken'));
    $transport = fakeTransport($http);

    expect(fn () => $transport->send(HttpMethod::Get, ['routes']))->toThrow(ConnectionException::class, 'GET /apisix/admin/routes: Connection refused')
        ->and(fn () => $transport->send(HttpMethod::Get, ['routes']))->toThrow(RequestException::class, 'broken');
});

it('exposes lower-cased response headers', function (): void {
    $http = fakeHttp()->json(200, [], ['X-Thing' => 'v']);

    expect(fakeTransport($http)->send(HttpMethod::Get)->header('x-THING'))->toBe('v');
});

it('rejects non-object bodies where an object is expected', function (): void {
    $http = fakeHttp()->json(200, [1, 2]);

    expect(fn () => fakeTransport($http)->send(HttpMethod::Get)->object())
        ->toThrow(UnexpectedResponseException::class, 'expected a JSON object body');
});
