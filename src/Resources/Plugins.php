<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginAttributes;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Subsystem;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Aybarsm\Apache\Apisix\AdminApi\Support\ResourceId;

/**
 * Spec tag `Plugins`: /apisix/admin/plugins.
 */
final readonly class Plugins extends Endpoint
{
    /**
     * Names of the enabled plugins, highest priority first.
     *
     * @return list<string>
     */
    #[SpecOperation('listPluginNames')]
    public function names(Subsystem $subsystem = Subsystem::Http): array
    {
        $response = $this->transport->send(HttpMethod::Get, ['plugins', 'list'], ['subsystem' => $subsystem->value]);

        return Data::of(['names' => $response->body], 'Plugin names')->stringList('names') ?? throw $response->unexpected('expected a JSON array of plugin names');
    }

    /**
     * Attributes (version, priority, schemas, ...) of every plugin, keyed by plugin name.
     *
     * @return array<string, PluginAttributes>
     */
    #[SpecOperation('listPluginAttributes')]
    public function attributes(Subsystem $subsystem = Subsystem::Http): array
    {
        $response = $this->transport->send(HttpMethod::Get, ['plugins'], ['all' => true, 'subsystem' => $subsystem->value]);

        $out = [];
        foreach ($response->object() as $name => $attributes) {
            $out[$name] = PluginAttributes::fromData(Data::of($attributes, 'Plugin attributes.'.$name));
        }

        return $out;
    }

    /**
     * JSON Schema of a plugin's configuration.
     *
     * @return array<string, mixed>
     */
    #[SpecOperation('getPluginSchema')]
    public function schema(string $pluginName, Subsystem $subsystem = Subsystem::Http): array
    {
        return $this->transport->send(
            HttpMethod::Get,
            ['plugins', ResourceId::assertName($pluginName, 'plugin name')],
            ['subsystem' => $subsystem->value],
        )->object();
    }

    /**
     * Reloads plugins on every APISIX node; returns the server's message (e.g. `done`).
     */
    #[SpecOperation('reloadPlugins')]
    public function reload(): string
    {
        $response = $this->transport->send(HttpMethod::Put, ['plugins', 'reload']);

        return is_string($response->body) ? $response->body : $response->raw;
    }
}
