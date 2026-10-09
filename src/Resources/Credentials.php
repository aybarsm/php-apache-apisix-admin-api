<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Credential;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Credentials`: /apisix/admin/consumers/{username}/credentials.
 *
 * Obtained via `$client->consumers()->credentials($username)`.
 *
 * @extends AbstractResource<Credential>
 */
final readonly class Credentials extends AbstractResource
{
    /** Query parameters accepted by `listCredentials`. */
    public const array LIST_PARAMS = ['name', 'label', 'page', 'page_size'];

    /**
     * @internal use {@see Consumers::credentials()}
     */
    public function __construct(
        Transport $transport,
        public string $username,
    ) {
        parent::__construct($transport);
    }

    /**
     * @return Page<Credential>
     */
    #[SpecOperation('listCredentials')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<Credential>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<Credential>
     */
    #[SpecOperation('getCredential')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param Credential|array<string, mixed> $credential
     *
     * @return Envelope<Credential>
     */
    #[SpecOperation('createCredentialById')]
    public function put(string|int $id, Credential|array $credential, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $credential, $ttl);
    }

    #[SpecOperation('deleteCredential')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['consumers', $this->username, 'credentials'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return Credential::class;
    }
}
