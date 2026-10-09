<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApacheApisixApiException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point: a factory for resource clients sharing one Transport.
 *
 *     $apisix = ApiClient::create('http://127.0.0.1:9180', $adminKey);
 *     foreach ($apisix->routes()->lazy() as $envelope) { ... }
 */
final readonly class ApiClient
{
    private Transport $transport;

    public function __construct(
        public ClientConfig $config,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $factory = new HttpFactory();
        $this->transport = new Transport(
            $config,
            $http ?? new GuzzleClient([
                'http_errors' => false,
                'timeout' => $config->timeout,
                'connect_timeout' => $config->connectTimeout,
            ]),
            $requestFactory ?? $factory,
            $streamFactory ?? $factory,
        );
    }

    public static function create(
        string $baseUri,
        #[\SensitiveParameter]
        string $apiKey,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        return new self(new ClientConfig($baseUri, $apiKey), $http, $requestFactory, $streamFactory);
    }

    /**
     * Unauthenticated liveness probe (`HEAD /apisix/admin`).
     */
    #[SpecOperation('ping')]
    public function ping(): bool
    {
        try {
            $this->transport->send(HttpMethod::Head, authenticate: false);

            return true;
        } catch (ApacheApisixApiException) {
            return false;
        }
    }
}
