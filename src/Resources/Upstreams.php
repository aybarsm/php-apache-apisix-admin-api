<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Upstreams`: /apisix/admin/upstreams.
 *
 * @extends AbstractResource<Upstream>
 */
final readonly class Upstreams extends AbstractResource
{
    /** Query parameters accepted by `listUpstreams`. */
    public const array LIST_PARAMS = ['name', 'label', 'page', 'page_size'];

    /**
     * @return Page<Upstream>
     */
    #[SpecOperation('listUpstreams')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<Upstream>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<Upstream>
     */
    #[SpecOperation('getUpstream')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param Upstream|array<string, mixed> $upstream
     *
     * @return Envelope<Upstream>
     */
    #[SpecOperation('createUpstream')]
    public function create(Upstream|array $upstream, ?int $ttl = null): Envelope
    {
        return $this->doCreate($upstream, $ttl);
    }

    /**
     * @param Upstream|array<string, mixed> $upstream
     *
     * @return Envelope<Upstream>
     */
    #[SpecOperation('createUpstreamById')]
    public function put(string|int $id, Upstream|array $upstream, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $upstream, $ttl);
    }

    /**
     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key
     *
     * @return Envelope<Upstream>
     */
    #[SpecOperation('updateUpstream')]
    public function patch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        return $this->doPatch($id, $changes, $ttl);
    }

    /**
     * Replaces the value at a slash-separated path, e.g. `plugins/limit-count`.
     *
     * @return Envelope<Upstream>
     */
    #[SpecOperation('patchUpstreamsSubPath')]
    public function patchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        return $this->doPatchPath($id, $path, $value, $ttl);
    }

    #[SpecOperation('deleteUpstream')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['upstreams'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return Upstream::class;
    }
}
