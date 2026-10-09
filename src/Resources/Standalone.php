<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\ConfigValidationError;
use Aybarsm\Apache\Apisix\AdminApi\Enums\HttpMethod;
use Aybarsm\Apache\Apisix\AdminApi\Enums\StandaloneUpdate;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\BadRequestException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Aybarsm\Apache\Apisix\AdminApi\Internal\JsonBody;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Response;
use Aybarsm\Apache\Apisix\AdminApi\Support\StandaloneSnapshot;
use JsonException;

/**
 * Spec tag `Standalone`: /apisix/admin/configs (API-driven standalone mode only).
 *
 * Configuration documents are arrays keyed by resource section (`routes`,
 * `upstreams`, `..._conf_version`, ...); items may be DTOs.
 */
final readonly class Standalone extends Endpoint
{
    public const string DIGEST_HEADER = 'X-Digest';

    public const string LAST_MODIFIED_HEADER = 'X-Last-Modified';

    /**
     * Whether the standalone endpoint is reachable, with the current snapshot metadata.
     */
    #[SpecOperation('headStandaloneConfig')]
    public function status(): StandaloneSnapshot
    {
        return $this->snapshot($this->transport->send(HttpMethod::Head, ['configs']), null);
    }

    /**
     * The accepted configuration snapshot and its metadata.
     */
    #[SpecOperation('getStandaloneConfig')]
    public function get(): StandaloneSnapshot
    {
        $response = $this->transport->send(HttpMethod::Get, ['configs']);

        return $this->snapshot($response, $response->object());
    }

    /**
     * Replaces the complete configuration snapshot.
     *
     * @param array<string, mixed> $config
     * @param string               $digest client-chosen identifier; change it whenever the content changes
     * @param int|float|null       $wait   milliseconds to wait for local worker sync (capped at 60000 by APISIX)
     */
    #[SpecOperation('putStandaloneConfig')]
    public function put(array $config, string $digest, int|float|null $wait = null): StandaloneUpdate
    {
        if (trim($digest) === '') {
            throw new InvalidArgumentException('The X-Digest value must not be empty.');
        }

        $response = $this->transport->send(
            HttpMethod::Put,
            ['configs'],
            ['wait' => $wait],
            new JsonBody($config),
            [self::DIGEST_HEADER => $digest],
        );

        return StandaloneUpdate::tryFrom($response->status) ?? throw $response->unexpected('unexpected success status');
    }

    /**
     * Validates a configuration document without applying it.
     *
     * @param array<string, mixed> $config
     *
     * @return list<ConfigValidationError> empty when the document is valid
     */
    #[SpecOperation('validateConfiguration')]
    public function validate(array $config): array
    {
        try {
            $this->transport->send(HttpMethod::Post, ['configs', 'validate'], body: new JsonBody($config));

            return [];
        } catch (BadRequestException $e) {
            try {
                $body = json_decode($e->rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw $e;
            }
            if (! is_array($body) || ! isset($body['errors'])) {
                throw $e;
            }

            return Data::of($body, 'ConfigValidationErrorResponse')->dtoList('errors', ConfigValidationError::class) ?? [];
        }
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function snapshot(Response $response, ?array $config): StandaloneSnapshot
    {
        $lastModified = $response->header(self::LAST_MODIFIED_HEADER);

        return new StandaloneSnapshot(
            digest: $response->header(self::DIGEST_HEADER),
            lastModified: $lastModified !== null && ctype_digit($lastModified) ? (int) $lastModified : null,
            config: $config,
        );
    }
}
