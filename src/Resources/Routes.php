<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Route;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Routes`: /apisix/admin/routes.
 *
 * @extends AbstractResource<Route>
 */
final readonly class Routes extends AbstractResource
{
    /** Query parameters accepted by `listRoutes`. */
    public const array LIST_PARAMS = ['name', 'label', 'uri', 'filter', 'page', 'page_size'];

    /**
     * @return Page<Route>
     */
    #[SpecOperation('listRoutes')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<Route>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<Route>
     */
    #[SpecOperation('getRoute')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param Route|array<string, mixed> $route
     *
     * @return Envelope<Route>
     */
    #[SpecOperation('createRoute')]
    public function create(Route|array $route, ?int $ttl = null): Envelope
    {
        return $this->doCreate($route, $ttl);
    }

    /**
     * @param Route|array<string, mixed> $route
     *
     * @return Envelope<Route>
     */
    #[SpecOperation('createRouteById')]
    public function put(string|int $id, Route|array $route, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $route, $ttl);
    }

    /**
     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key
     *
     * @return Envelope<Route>
     */
    #[SpecOperation('updateRoute')]
    public function patch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        return $this->doPatch($id, $changes, $ttl);
    }

    /**
     * Replaces the value at a slash-separated path, e.g. `plugins/limit-count`.
     *
     * @return Envelope<Route>
     */
    #[SpecOperation('patchRoutesSubPath')]
    public function patchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        return $this->doPatchPath($id, $path, $value, $ttl);
    }

    #[SpecOperation('deleteRoute')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['routes'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return Route::class;
    }
}
