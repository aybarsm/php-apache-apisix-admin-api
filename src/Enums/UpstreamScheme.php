<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec `Upstream.scheme` values.
 */
enum UpstreamScheme: string
{
    case Grpc = 'grpc';
    case Grpcs = 'grpcs';
    case Http = 'http';
    case Https = 'https';
    case Ws = 'ws';
    case Wss = 'wss';
    case Tcp = 'tcp';
    case Tls = 'tls';
    case Udp = 'udp';
    case Kafka = 'kafka';
}
