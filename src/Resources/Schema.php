<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Enums\ResourceKind;
use Aybarsm\Apache\Apisix\AdminApi\Internal\JsonBody;
use Aybarsm\Apache\Apisix\AdminApi\Support\ResourceId;

/**
 * Spec tag `Schema Validation`: /apisix/admin/schema.
 */
final readonly class Schema extends Endpoint
{
    /**
     * JSON Schema APISIX uses to validate a resource kind.
     *
     * @return array<string, mixed>
     */
    #[SpecOperation('getResourceSchema')]
    public function resource(ResourceKind $kind): array
    {
        return $this->transport->send(HttpMethod::Get, ['schema', $kind->value])->object();
    }

    /**
     * Validates a resource configuration without storing it.
     *
     * @param Dto|array<string, mixed> $config
     *
     * @throws \Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException when the configuration is invalid
     */
    #[SpecOperation('validateResourceSchema')]
    public function validate(ResourceKind $kind, Dto|array $config): void
    {
        $this->transport->send(HttpMethod::Post, ['schema', 'validate', $kind->value], body: new JsonBody($config instanceof Dto ? $config->toRequest() : $config));
    }

    /**
     * JSON Schema of a plugin's configuration.
     *
     * @return array<string, mixed>
     */
    #[SpecOperation('getPluginSchemaAlt')]
    public function plugin(string $pluginName): array
    {
        return $this->transport->send(HttpMethod::Get, ['schema', 'plugins', ResourceId::assertName($pluginName, 'plugin name')])->object();
    }
}
