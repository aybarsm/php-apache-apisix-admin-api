<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Outcome of `putStandaloneConfig`, backed by its success status code.
 */
enum StandaloneUpdate: int
{
    /** With a positive wait, all local workers reported the submitted digest. */
    case Synced = 200;

    /** Snapshot accepted; no wait requested, or the wait expired before sync was confirmed. */
    case Accepted = 202;

    /** The digest matches the stored snapshot; nothing was updated. */
    case Unchanged = 204;
}
