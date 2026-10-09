<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec parameter `SecretType` (`/apisix/admin/secrets/{secret_type}`).
 */
enum SecretType: string
{
    case Vault = 'vault';
    case Aws = 'aws';
    case Gcp = 'gcp';
}
