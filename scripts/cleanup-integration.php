<?php

declare(strict_types=1);

/*
 * Deletes resources left behind by interrupted integration runs: anything
 * labelled suite=apisix-php or whose id starts with "it-"/"it_".
 *
 * Usage: APISIX_ADMIN_KEY=... php scripts/cleanup-integration.php [--dry-run]
 */

use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApacheApisixApiException;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\Env;

require dirname(__DIR__).'/vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv, true);
$key = Env::adminKey() ?? '';
if ($key === '') {
    fwrite(STDERR, "APISIX_ADMIN_KEY is not set.\n");
    exit(1);
}

$apisix = ApiClient::create(Env::adminUrl(), $key);
$isFixture = static fn (string $id, ?array $labels): bool => str_starts_with($id, 'it-') || str_starts_with($id, 'it_') || ($labels['suite'] ?? null) === 'apisix-php';

// Dependants first, so referenced upstreams/services can be removed.
$resources = [
    'routes' => $apisix->routes(),
    'stream_routes' => $apisix->streamRoutes(),
    'services' => $apisix->services(),
    'upstreams' => $apisix->upstreams(),
    'ssls' => $apisix->ssls(),
    'protos' => $apisix->protos(),
    'plugin_configs' => $apisix->pluginConfigs(),
    'consumer_groups' => $apisix->consumerGroups(),
    'global_rules' => $apisix->globalRules(),
    'consumers' => $apisix->consumers(),
    'secrets/vault' => $apisix->secrets()->vault(),
];

$deleted = 0;
foreach ($resources as $name => $resource) {
    try {
        $ids = [];
        foreach ($resource->lazy() as $envelope) {
            $labels = property_exists($envelope->value, 'labels') ? $envelope->value->labels : null;
            if ($isFixture($envelope->id(), $labels)) {
                $ids[] = $envelope->id();
            }
        }
        foreach ($ids as $id) {
            echo ($dryRun ? '[dry-run] ' : '')."delete {$name}/{$id}\n";
            if (! $dryRun) {
                $resource->delete($id, force: true);
            }
            $deleted++;
        }
    } catch (ApacheApisixApiException $e) {
        fwrite(STDERR, "{$name}: {$e->getMessage()}\n");
    }
}

echo sprintf("%s %d fixture resource(s).\n", $dryRun ? 'Would delete' : 'Deleted', $deleted);
