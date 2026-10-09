<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Exceptions;

/**
 * The Admin API could not be reached (DNS, refused connection, timeout, ...).
 */
final class ConnectionException extends TransportException {}
