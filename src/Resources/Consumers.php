<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Consumer;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Support\ResourceId;
use Generator;
use Override;

/**
 * Spec tag `Consumers`: /apisix/admin/consumers.
 *
 * Consumers are identified by `username`, which is sent in the body of a PUT
 * to the collection (there is no POST or PATCH).
 *
 * @extends AbstractResource<Consumer>
 */
final readonly class Consumers extends AbstractResource
{
    /** Query parameters accepted by `listConsumers`. */
    public const array LIST_PARAMS = ['label', 'page', 'page_size'];

    /**
     * @return Page<Consumer>
     */
    #[SpecOperation('listConsumers')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<Consumer>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<Consumer>
     */
    #[SpecOperation('getConsumer')]
    public function get(string $username): Envelope
    {
        return $this->doGet($username);
    }

    /**
     * Creates or replaces the consumer named by `username` in the body.
     *
     * @param Consumer|array<string, mixed> $consumer
     *
     * @return Envelope<Consumer>
     */
    #[SpecOperation('createConsumer')]
    public function put(Consumer|array $consumer, ?int $ttl = null): Envelope
    {
        return $this->envelope($this->transport->send(HttpMethod::Put, $this->basePath(), $this->ttl($ttl), $this->body($consumer)));
    }

    #[SpecOperation('deleteConsumer')]
    public function delete(string $username, bool $force = false): DeleteResult
    {
        return $this->doDelete($username, $force);
    }

    /**
     * Credentials of one consumer (`/consumers/{username}/credentials`).
     */
    public function credentials(string $username): Credentials
    {
        return new Credentials($this->transport, ResourceId::assertUsername($username));
    }

    #[Override]
    protected function itemPath(string|int $id): array
    {
        return [...$this->basePath(), ResourceId::assertUsername((string) $id)];
    }

    #[Override]
    protected function basePath(): array
    {
        return ['consumers'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return Consumer::class;
    }
}
