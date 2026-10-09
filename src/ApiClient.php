<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApacheApisixApiException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;
use Aybarsm\Apache\Apisix\AdminApi\Resources\ConsumerGroups;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Consumers;
use Aybarsm\Apache\Apisix\AdminApi\Resources\GlobalRules;
use Aybarsm\Apache\Apisix\AdminApi\Resources\PluginConfigs;
use Aybarsm\Apache\Apisix\AdminApi\Resources\PluginMetadata;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Plugins;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Protos;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Routes;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Secrets;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Services;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Ssls;
use Aybarsm\Apache\Apisix\AdminApi\Resources\StreamRoutes;
use Aybarsm\Apache\Apisix\AdminApi\Resources\Upstreams;
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

    public function routes(): Routes
    {
        return new Routes($this->transport);
    }

    public function services(): Services
    {
        return new Services($this->transport);
    }

    public function upstreams(): Upstreams
    {
        return new Upstreams($this->transport);
    }

    public function streamRoutes(): StreamRoutes
    {
        return new StreamRoutes($this->transport);
    }

    public function protos(): Protos
    {
        return new Protos($this->transport);
    }

    public function consumers(): Consumers
    {
        return new Consumers($this->transport);
    }

    public function consumerGroups(): ConsumerGroups
    {
        return new ConsumerGroups($this->transport);
    }

    public function pluginConfigs(): PluginConfigs
    {
        return new PluginConfigs($this->transport);
    }

    public function globalRules(): GlobalRules
    {
        return new GlobalRules($this->transport);
    }

    public function ssls(): Ssls
    {
        return new Ssls($this->transport);
    }

    public function secrets(): Secrets
    {
        return new Secrets($this->transport);
    }

    public function pluginMetadata(): PluginMetadata
    {
        return new PluginMetadata($this->transport);
    }

    public function plugins(): Plugins
    {
        return new Plugins($this->transport);
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
