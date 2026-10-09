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

    /**
     * Singular name APISIX uses for `GET /schema/{name}` (its `core.schema` keys).
     */
    public function schemaName(): string
    {
        return match ($this) {
            self::Routes => 'route',
            self::Services => 'service',
            self::Upstreams => 'upstream',
            self::Consumers => 'consumer',
            self::Ssls => 'ssl',
            self::PluginConfigs => 'plugin_config',
            self::GlobalRules => 'global_rule',
            self::StreamRoutes => 'stream_route',
            self::Protos => 'proto',
            self::ConsumerGroups => 'consumer_group',
            self::Credentials => 'credential',
        };
    }
}
