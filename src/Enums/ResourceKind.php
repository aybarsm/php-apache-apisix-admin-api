<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Enums;

/**
 * Spec parameter `ResourceKindInPath` (schema endpoints).
 */
enum ResourceKind: string
{
    case Routes = 'routes';
    case Services = 'services';
    case Upstreams = 'upstreams';
    case Consumers = 'consumers';
    case Ssls = 'ssls';
    case PluginConfigs = 'plugin_configs';
    case GlobalRules = 'global_rules';
    case StreamRoutes = 'stream_routes';
    case Protos = 'protos';
    case ConsumerGroups = 'consumer_groups';
    case Credentials = 'credentials';
}
