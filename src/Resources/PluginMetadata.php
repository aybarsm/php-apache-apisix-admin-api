<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\PluginMetadata as PluginMetadataDto;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Support\ResourceId;
use Generator;
use Override;

/**
 * Spec tag `Plugin Metadata`: /apisix/admin/plugin_metadata, keyed by plugin name.
 *
 * @extends AbstractResource<PluginMetadataDto>
 */
final readonly class PluginMetadata extends AbstractResource
{
    /** Query parameters accepted by `listPluginMetadata`. */
    public const array LIST_PARAMS = ['page', 'page_size'];

    /**
     * @return Page<PluginMetadataDto>
     */
    #[SpecOperation('listPluginMetadata')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<PluginMetadataDto>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<PluginMetadataDto>
     */
    #[SpecOperation('getPluginMetadata')]
    public function get(string $pluginName): Envelope
    {
        return $this->doGet($pluginName);
    }

    /**
     * @param PluginMetadataDto|array<string, mixed> $metadata
     *
     * @return Envelope<PluginMetadataDto>
     */
    #[SpecOperation('createPluginMetadata')]
    public function put(string $pluginName, PluginMetadataDto|array $metadata, ?int $ttl = null): Envelope
    {
        return $this->doPut($pluginName, $metadata, $ttl);
    }

    #[SpecOperation('deletePluginMetadata')]
    public function delete(string $pluginName, bool $force = false): DeleteResult
    {
        return $this->doDelete($pluginName, $force);
    }

    #[Override]
    protected function itemPath(string|int $id): array
    {
        return [...$this->basePath(), ResourceId::assertName((string) $id, 'plugin name')];
    }

    #[Override]
    protected function basePath(): array
    {
        return ['plugin_metadata'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return PluginMetadataDto::class;
    }
}
