<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\StreamRoute;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Stream Routes`: /apisix/admin/stream_routes.
 *
 * @extends AbstractResource<StreamRoute>
 */
final readonly class StreamRoutes extends AbstractResource
{
    /** Query parameters accepted by `listStreamRoutes`. */
    public const array LIST_PARAMS = ['name', 'label', 'filter', 'page', 'page_size'];

    /**
     * @return Page<StreamRoute>
     */
    #[SpecOperation('listStreamRoutes')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<StreamRoute>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<StreamRoute>
     */
    #[SpecOperation('getStreamRoute')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param StreamRoute|array<string, mixed> $streamRoute
     *
     * @return Envelope<StreamRoute>
     */
    #[SpecOperation('createStreamRoute')]
    public function create(StreamRoute|array $streamRoute, ?int $ttl = null): Envelope
    {
        return $this->doCreate($streamRoute, $ttl);
    }

    /**
     * @param StreamRoute|array<string, mixed> $streamRoute
     *
     * @return Envelope<StreamRoute>
     */
    #[SpecOperation('putStreamRoute')]
    public function put(string|int $id, StreamRoute|array $streamRoute, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $streamRoute, $ttl);
    }

    #[SpecOperation('deleteStreamRoute')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['stream_routes'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return StreamRoute::class;
    }
}
