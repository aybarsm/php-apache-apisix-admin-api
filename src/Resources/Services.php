<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Service;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Services`: /apisix/admin/services.
 *
 * @extends AbstractResource<Service>
 */
final readonly class Services extends AbstractResource
{
    /** Query parameters accepted by `listServices`. */
    public const array LIST_PARAMS = ['name', 'label', 'page', 'page_size'];

    /**
     * @return Page<Service>
     */
    #[SpecOperation('listServices')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<Service>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<Service>
     */
    #[SpecOperation('getService')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param Service|array<string, mixed> $service
     *
     * @return Envelope<Service>
     */
    #[SpecOperation('createService')]
    public function create(Service|array $service, ?int $ttl = null): Envelope
    {
        return $this->doCreate($service, $ttl);
    }

    /**
     * @param Service|array<string, mixed> $service
     *
     * @return Envelope<Service>
     */
    #[SpecOperation('createServiceById')]
    public function put(string|int $id, Service|array $service, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $service, $ttl);
    }

    /**
     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key
     *
     * @return Envelope<Service>
     */
    #[SpecOperation('updateService')]
    public function patch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        return $this->doPatch($id, $changes, $ttl);
    }

    /**
     * Replaces the value at a slash-separated path, e.g. `plugins/limit-count`.
     *
     * @return Envelope<Service>
     */
    #[SpecOperation('patchServicesSubPath')]
    public function patchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        return $this->doPatchPath($id, $path, $value, $ttl);
    }

    #[SpecOperation('deleteService')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['services'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return Service::class;
    }
}
