<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Internal\Transport;

/**
 * Base of every resource client: holds the shared Transport.
 *
 * Non-CRUD endpoints (plugins, schema, standalone, ...) extend this directly;
 * etcd-backed CRUD resources extend {@see AbstractResource}.
 */
abstract readonly class Endpoint
{
    /**
     * @internal resources are obtained from {@see \Aybarsm\Apache\Apisix\AdminApi\ApiClient}
     */
    public function __construct(
        protected Transport $transport,
    ) {}
}
