<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\JsonBody;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Pagination;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Response;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Support\ResourceId;
use Generator;

/**
 * Shared implementation of the etcd-backed CRUD resources.
 *
 * Concrete resources declare thin public methods carrying `#[SpecOperation]`
 * and delegate to the protected `do*()` helpers, so each resource exposes only
 * the verbs its spec defines.
 *
 * @template T of Dto
 */
abstract readonly class AbstractResource extends Endpoint
{
    /** Page size used by `lazy()` when the query does not set one. */
    public const int DEFAULT_PAGE_SIZE = 100;

    /**
     * Path segments of the collection below `/apisix/admin`.
     *
     * @return list<string>
     */
    abstract protected function basePath(): array;

    /**
     * @return class-string<T>
     */
    abstract protected function dtoClass(): string;

    /**
     * @param list<string> $supported query parameters the list operation declares
     *
     * @return Page<T>
     */
    protected function doList(?ListQuery $query, array $supported): Page
    {
        $query ??= new ListQuery();
        $response = $this->transport->send(HttpMethod::Get, $this->basePath(), $query->toQuery($supported));

        return Page::fromArray($response->object(), $this->dtoClass(), $query->page, $query->pageSize, $this->label().' list');
    }

    /**
     * Iterates every item across pages, fetching one page per request.
     *
     * @param list<string> $supported
     *
     * @return Generator<int, Envelope<T>, mixed, void>
     */
    protected function doLazy(?ListQuery $query, array $supported): Generator
    {
        return Pagination::lazy(fn (ListQuery $q): Page => $this->doList($q, $supported), $query, $supported, static::DEFAULT_PAGE_SIZE);
    }

    /**
     * @return Envelope<T>
     */
    protected function doGet(string|int $id): Envelope
    {
        return $this->envelope($this->transport->send(HttpMethod::Get, $this->itemPath($id)));
    }

    /**
     * POST to the collection; APISIX generates the id.
     *
     * @param T|array<string, mixed> $data
     *
     * @return Envelope<T>
     */
    protected function doCreate(Dto|array $data, ?int $ttl = null): Envelope
    {
        return $this->envelope($this->transport->send(HttpMethod::Post, $this->basePath(), $this->ttl($ttl), $this->body($data)));
    }

    /**
     * PUT by id: create or fully replace.
     *
     * @param T|array<string, mixed> $data
     *
     * @return Envelope<T>
     */
    protected function doPut(string|int $id, Dto|array $data, ?int $ttl = null): Envelope
    {
        return $this->envelope($this->transport->send(HttpMethod::Put, $this->itemPath($id), $this->ttl($ttl), $this->body($data)));
    }

    /**
     * PATCH by id with a JSON merge-patch (`null` removes a key).
     *
     * @param array<string, mixed> $changes
     *
     * @return Envelope<T>
     */
    protected function doPatch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        if ($changes === []) {
            throw new InvalidArgumentException('Patch requires at least one change.');
        }

        return $this->envelope($this->transport->send(HttpMethod::Patch, $this->itemPath($id), $this->ttl($ttl), new JsonBody($changes)));
    }

    /**
     * PATCH a nested value at a slash-separated path (e.g. `plugins/limit-count`), replacing it.
     *
     * @return Envelope<T>
     */
    protected function doPatchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        $segments = explode('/', trim($path, '/'));
        if ($path === '' || in_array('', $segments, true)) {
            throw new InvalidArgumentException(sprintf('Invalid sub path "%s".', $path));
        }

        $response = $this->transport->send(
            HttpMethod::Patch,
            [...$this->itemPath($id), ...$segments],
            $this->ttl($ttl),
            new JsonBody($value instanceof Dto ? $value->jsonSerialize() : $value),
        );

        return $this->envelope($response);
    }

    protected function doDelete(string|int $id, bool $force = false): DeleteResult
    {
        $response = $this->transport->send(HttpMethod::Delete, $this->itemPath($id), ['force' => $force ? true : null]);

        return DeleteResult::fromArray($response->object());
    }

    /**
     * @return list<string>
     */
    protected function itemPath(string|int $id): array
    {
        return [...$this->basePath(), ResourceId::assert($id)];
    }

    /**
     * @return Envelope<T>
     */
    protected function envelope(Response $response): Envelope
    {
        return Envelope::fromArray($response->object(), $this->dtoClass(), $this->label());
    }

    /**
     * @param Dto|array<string, mixed> $data
     */
    protected function body(Dto|array $data): JsonBody
    {
        return new JsonBody($data instanceof Dto ? $data->toRequest() : $data);
    }

    /**
     * @return array<string, int|null>
     */
    protected function ttl(?int $ttl): array
    {
        if ($ttl !== null && $ttl < 1) {
            throw new InvalidArgumentException(sprintf('ttl must be >= 1, got %d.', $ttl));
        }

        return ['ttl' => $ttl];
    }

    private function label(): string
    {
        $class = $this->dtoClass();
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
