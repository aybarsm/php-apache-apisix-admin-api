<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

use Aybarsm\Apache\Apisix\AdminApi\ClientConfig;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ConnectionException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\RequestException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The single HTTP gateway of the client: builds requests, authenticates,
 * encodes/decodes JSON and maps failures onto the exception hierarchy.
 *
 * @internal
 */
final readonly class Transport
{
    public const string ADMIN_PREFIX = '/apisix/admin';

    public const string API_KEY_HEADER = 'X-API-KEY';

    public const string JSON_CONTENT_TYPE = 'application/json';

    public const int JSON_ENCODE_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(
        private ClientConfig $config,
        private ClientInterface $http,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
    ) {}

    /**
     * @param list<string|int>                          $segments path segments below /apisix/admin (each is URL-encoded)
     * @param array<string, string|int|float|bool|null> $query    null values are dropped
     * @param array<string, string>                     $headers
     */
    public function send(
        HttpMethod $method,
        array $segments = [],
        array $query = [],
        ?JsonBody $body = null,
        array $headers = [],
        bool $authenticate = true,
    ): Response {
        $target = $this->target($segments, $query);
        $request = $this->requests->createRequest($method->value, $this->config->baseUri.$target)
            ->withHeader('Accept', self::JSON_CONTENT_TYPE)
            ->withHeader('User-Agent', $this->config->userAgent);

        foreach ([...$this->config->headers, ...$headers] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($authenticate && $this->config->apiKey !== '') {
            $request = $request->withHeader(self::API_KEY_HEADER, $this->config->apiKey);
        }

        if ($body !== null) {
            try {
                $encoded = json_encode($body->value, self::JSON_ENCODE_FLAGS);
            } catch (JsonException $e) {
                throw new InvalidArgumentException(sprintf('Request body is not JSON-encodable: %s', $e->getMessage()), 0, $e);
            }
            $request = $request
                ->withHeader('Content-Type', self::JSON_CONTENT_TYPE)
                ->withBody($this->streams->createStream($encoded));
        }

        try {
            $psrResponse = $this->http->sendRequest($request);
        } catch (NetworkExceptionInterface $e) {
            throw new ConnectionException($method->value, $target, $e->getMessage(), $e);
        } catch (ClientExceptionInterface $e) {
            throw new RequestException($method->value, $target, $e->getMessage(), $e);
        }

        $response = $this->decode($method, $target, $psrResponse);

        if ($response->status >= 400) {
            throw ExceptionFactory::fromResponse($response);
        }

        return $response;
    }

    /**
     * @param list<string|int>                          $segments
     * @param array<string, string|int|float|bool|null> $query
     */
    private function target(array $segments, array $query): string
    {
        $path = self::ADMIN_PREFIX;
        foreach ($segments as $segment) {
            $segment = (string) $segment;
            if ($segment === '') {
                throw new InvalidArgumentException('Path segments must not be empty.');
            }
            $path .= '/'.rawurlencode($segment);
        }

        $params = [];
        foreach ($query as $name => $value) {
            if ($value !== null) {
                $params[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            }
        }

        return $params === [] ? $path : $path.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function decode(HttpMethod $method, string $target, ResponseInterface $psr): Response
    {
        $raw = (string) $psr->getBody();
        $headers = [];
        foreach ($psr->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values($values);
        }

        $contentType = strtolower($psr->getHeaderLine('Content-Type'));
        $trimmed = ltrim($raw);
        $looksJson = $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[');
        $body = $raw === '' ? null : $raw;

        if ($raw !== '' && (str_contains($contentType, 'json') || $looksJson)) {
            try {
                $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                // e.g. plugins/reload answers `done` as application/json; callers needing an object still fail
                return new Response($method->value, $target, $psr->getStatusCode(), $headers, $raw, $raw, $e->getMessage());
            }
        }

        return new Response($method->value, $target, $psr->getStatusCode(), $headers, $body, $raw);
    }
}
