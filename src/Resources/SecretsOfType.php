<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\AwsSecret;
use Aybarsm\Apache\Apisix\AdminApi\Dto\GcpSecret;
use Aybarsm\Apache\Apisix\AdminApi\Dto\VaultSecret;
use Aybarsm\Apache\Apisix\AdminApi\Enums\SecretType;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Secrets`: /apisix/admin/secrets/{secret_type} for one secret manager type.
 *
 * Obtained via `$client->secrets()->vault()` (or `aws()`, `gcp()`, `of()`).
 *
 * @extends AbstractResource<VaultSecret|AwsSecret|GcpSecret>
 */
final readonly class SecretsOfType extends AbstractResource
{
    /** Query parameters accepted by `listSecretsByType` (none declared). */
    public const array LIST_PARAMS = [];

    /**
     * @internal use {@see Secrets::of()}
     */
    public function __construct(
        Transport $transport,
        public SecretType $type,
    ) {
        parent::__construct($transport);
    }

    /**
     * @return class-string<VaultSecret|AwsSecret|GcpSecret>
     */
    public static function dtoClassFor(SecretType $type): string
    {
        return match ($type) {
            SecretType::Vault => VaultSecret::class,
            SecretType::Aws => AwsSecret::class,
            SecretType::Gcp => GcpSecret::class,
        };
    }

    /**
     * @return Page<VaultSecret|AwsSecret|GcpSecret>
     */
    #[SpecOperation('listSecretsByType')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every secret of this type (the endpoint is not paginated).
     *
     * @return Generator<int, Envelope<VaultSecret|AwsSecret|GcpSecret>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<VaultSecret|AwsSecret|GcpSecret>
     */
    #[SpecOperation('getSecret')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param VaultSecret|AwsSecret|GcpSecret|array<string, mixed> $secret must match this resource's type
     *
     * @return Envelope<VaultSecret|AwsSecret|GcpSecret>
     */
    #[SpecOperation('createSecretById')]
    public function put(string|int $id, VaultSecret|AwsSecret|GcpSecret|array $secret, ?int $ttl = null): Envelope
    {
        if (! is_array($secret) && ! $secret instanceof ($this->dtoClass())) {
            throw new InvalidArgumentException(sprintf('A %s secret resource cannot store %s.', $this->type->value, $secret::class));
        }

        return $this->doPut($id, $secret, $ttl);
    }

    /**
     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key
     *
     * @return Envelope<VaultSecret|AwsSecret|GcpSecret>
     */
    #[SpecOperation('updateSecret')]
    public function patch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        return $this->doPatch($id, $changes, $ttl);
    }

    /**
     * Replaces the value at a slash-separated path, e.g. `token`.
     *
     * @return Envelope<VaultSecret|AwsSecret|GcpSecret>
     */
    #[SpecOperation('patchSecretSubPath')]
    public function patchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        return $this->doPatchPath($id, $path, $value, $ttl);
    }

    #[SpecOperation('deleteSecret')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['secrets', $this->type->value];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return self::dtoClassFor($this->type);
    }
}
