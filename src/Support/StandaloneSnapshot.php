<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Support;

/**
 * Standalone configuration metadata (`X-Digest` / `X-Last-Modified` headers),
 * plus the configuration document when it was requested.
 */
final readonly class StandaloneSnapshot
{
    /**
     * @param array<string, mixed>|null $config the accepted configuration (null for HEAD)
     */
    public function __construct(
        public ?string $digest = null,
        public ?int $lastModified = null,
        public ?array $config = null,
    ) {}
}
