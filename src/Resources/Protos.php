<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Proto;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Protos`: /apisix/admin/protos.
 *
 * @extends AbstractResource<Proto>
 */
final readonly class Protos extends AbstractResource
{
    /** Query parameters accepted by `listProtos`. */
    public const array LIST_PARAMS = ['name', 'label', 'page', 'page_size'];

    /**
     * @return Page<Proto>
     */
    #[SpecOperation('listProtos')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<Proto>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<Proto>
     */
    #[SpecOperation('getProto')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param Proto|array<string, mixed> $proto
     *
     * @return Envelope<Proto>
     */
    #[SpecOperation('createProto')]
    public function create(Proto|array $proto, ?int $ttl = null): Envelope
    {
        return $this->doCreate($proto, $ttl);
    }

    /**
     * @param Proto|array<string, mixed> $proto
     *
     * @return Envelope<Proto>
     */
    #[SpecOperation('createProtoById')]
    public function put(string|int $id, Proto|array $proto, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $proto, $ttl);
    }

    #[SpecOperation('deleteProto')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['protos'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return Proto::class;
    }
}
