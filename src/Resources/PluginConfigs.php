<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginConfig;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Plugin Configs`: /apisix/admin/plugin_configs.
 *
 * @extends AbstractResource<PluginConfig>
 */
final readonly class PluginConfigs extends AbstractResource
{
    /** Query parameters accepted by `listPluginConfigs`. */
    public const array LIST_PARAMS = ['name', 'label', 'page', 'page_size'];

    /**
     * @return Page<PluginConfig>
     */
    #[SpecOperation('listPluginConfigs')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<PluginConfig>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<PluginConfig>
     */
    #[SpecOperation('getPluginConfig')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param PluginConfig|array<string, mixed> $pluginConfig
     *
     * @return Envelope<PluginConfig>
     */
    #[SpecOperation('createPluginConfigById')]
    public function put(string|int $id, PluginConfig|array $pluginConfig, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $pluginConfig, $ttl);
    }

    /**
     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key
     *
     * @return Envelope<PluginConfig>
     */
    #[SpecOperation('updatePluginConfig')]
    public function patch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        return $this->doPatch($id, $changes, $ttl);
    }

    /**
     * Replaces the value at a slash-separated path, e.g. `plugins/limit-count`.
     *
     * @return Envelope<PluginConfig>
     */
    #[SpecOperation('patchPluginConfigsSubPath')]
    public function patchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        return $this->doPatchPath($id, $path, $value, $ttl);
    }

    #[SpecOperation('deletePluginConfig')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['plugin_configs'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return PluginConfig::class;
    }
}
