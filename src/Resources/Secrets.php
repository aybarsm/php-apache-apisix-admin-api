<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\AwsSecret;
use Aybarsm\Apache\Apisix\AdminApi\Dto\GcpSecret;
use Aybarsm\Apache\Apisix\AdminApi\Dto\VaultSecret;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Enums\SecretType;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\HydrationException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Pagination;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;

/**
 * Spec tag `Secrets`: /apisix/admin/secrets.
 *
 * Lists secret manager configurations of every type; per-type operations live
 * on {@see SecretsOfType} (`vault()`, `aws()`, `gcp()` or `of()`).
 */
final readonly class Secrets extends Endpoint
{
    /** Query parameters accepted by `listSecrets`. */
    public const array LIST_PARAMS = ['page', 'page_size'];

    /** Page size used by `lazy()` when the query does not set one. */
    public const int DEFAULT_PAGE_SIZE = 100;

    /**
     * Secrets of all types; each item is hydrated according to the type in its key.
     *
     * @return Page<VaultSecret|AwsSecret|GcpSecret>
     */
    #[SpecOperation('listSecrets')]
    public function list(?ListQuery $query = null): Page
    {
        $query ??= new ListQuery();
        $data = Data::of($this->transport->send(HttpMethod::Get, ['secrets'], $query->toQuery(self::LIST_PARAMS))->object(), 'Secret list');

        $items = [];
        foreach ($data->list('list') ?? [] as $i => $item) {
            $path = sprintf('Secret list.list[%d]', $i);
            $item = Data::assertMap($item, $path);
            $key = is_string($item['key'] ?? null) ? $item['key'] : '';
            $items[] = Envelope::fromArray($item, SecretsOfType::dtoClassFor(self::typeFromKey($key, $path)), $path);
        }

        return new Page($data->int('total') ?? count($items), $items, $query->page, $query->pageSize);
    }

    /**
     * Every secret across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<VaultSecret|AwsSecret|GcpSecret>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return Pagination::lazy($this->list(...), $query, self::LIST_PARAMS, self::DEFAULT_PAGE_SIZE);
    }

    public function of(SecretType $type): SecretsOfType
    {
        return new SecretsOfType($this->transport, $type);
    }

    public function vault(): SecretsOfType
    {
        return $this->of(SecretType::Vault);
    }

    public function aws(): SecretsOfType
    {
        return $this->of(SecretType::Aws);
    }

    public function gcp(): SecretsOfType
    {
        return $this->of(SecretType::Gcp);
    }

    /**
     * `/apisix/secrets/{type}/{id}` => SecretType.
     */
    private static function typeFromKey(string $key, string $path): SecretType
    {
        $segments = explode('/', trim($key, '/'));
        $type = $segments[count($segments) - 2] ?? '';

        return SecretType::tryFrom($type) ?? throw new HydrationException($path.'.key', sprintf('cannot infer the secret type from "%s"', $key));
    }
}
