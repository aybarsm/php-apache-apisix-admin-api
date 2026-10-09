# Apache APISIX Admin API client for PHP

This is a typed client for the [Apache APISIX](https://apisix.apache.org/) Admin REST API. It does not depend on any framework, and it is organised around the API's resources.

- **Source of truth.** The client is built from the OpenAPI spec in [`resources/apache-apisix/`](resources/apache-apisix/), currently APISIX **3.19.0**. Every implemented operation is mapped and checked against the spec.
- **Return types.** Methods return readonly DTOs, envelopes and pages. They never return PSR-7 responses.
- **Pagination.** Lists can be fetched page by page or iterated lazily with a generator.
- **Errors.** Every failure is raised as an exception from a single `ApacheApisixApiException` hierarchy.
- **HTTP client.** Requests go through any PSR-18 client. Guzzle is used by default.

## Requirements

- PHP 8.3 or later, with `ext-json`
- APISIX with the Admin API enabled (`deployment.admin`)

## Installation

```bash
composer require aybarsm/apache-apisix-admin-api
```

## Quick start

```php
use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Route;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Upstream;
use Aybarsm\Apache\Apisix\AdminApi\Enums\UpstreamType;

$apisix = ApiClient::create('http://127.0.0.1:9180', getenv('APISIX_ADMIN_KEY'));

$apisix->upstreams()->put('httpbin', new Upstream(
    type: UpstreamType::Roundrobin,
    nodes: ['httpbin.org:80' => 1],
));

$envelope = $apisix->routes()->put('ip', new Route(
    uri: '/ip',
    methods: ['GET'],
    upstreamId: 'httpbin',
));

$envelope->id();          // "ip"
$envelope->value->uri;    // "/ip"
$envelope->modifiedIndex; // etcd revision
```

## Resources

| Accessor | APISIX path | Verbs |
|---|---|---|
| `routes()` | `/routes` | list, lazy, get, create, put, patch, patchPath, delete |
| `services()` | `/services` | list, lazy, get, create, put, patch, patchPath, delete |
| `upstreams()` | `/upstreams` | list, lazy, get, create, put, patch, patchPath, delete |
| `ssls()` | `/ssls` | list, lazy, get, create, put, patch, patchPath, delete |
| `streamRoutes()` | `/stream_routes` | list, lazy, get, create, put, delete |
| `protos()` | `/protos` | list, lazy, get, create, put, delete |
| `globalRules()` | `/global_rules` | list, lazy, get, put, patch, patchPath, delete |
| `consumerGroups()` | `/consumer_groups` | list, lazy, get, put, patch, patchPath, delete |
| `pluginConfigs()` | `/plugin_configs` | list, lazy, get, put, patch, patchPath, delete |
| `consumers()` | `/consumers` | list, lazy, get, put (username in body), delete, `credentials($username)` |
| `consumers()->credentials($u)` | `/consumers/{u}/credentials` | list, lazy, get, put, delete |
| `secrets()` | `/secrets` | list, lazy (all types), `vault()`, `aws()`, `gcp()`, `of(SecretType)` |
| `secrets()->vault()` and the other types | `/secrets/{type}` | list, lazy, get, put, patch, patchPath, delete |
| `pluginMetadata()` | `/plugin_metadata` | list, lazy, get, put, delete |
| `plugins()` | `/plugins` | `names()`, `attributes()`, `schema($name)`, `reload()` |
| `schema()` | `/schema` | `resource(ResourceKind)`, `validate(ResourceKind, $config)`, `plugin($name)` |
| `standalone()` | `/configs` | `status()`, `get()`, `put($config, $digest, $wait)`, `validate($config)` |
| `ping()` | `HEAD /apisix/admin` | unauthenticated liveness check, returns `bool` |

Each resource exposes only the verbs its spec declares. For example, global rules have no `create()` because the API has no `POST` for them.

### Return types

- `get`, `create`, `put`, `patch` and `patchPath` return an `Envelope<T>` with `key`, `value` (the DTO), `createdIndex`, `modifiedIndex` and an `id()` helper.
- `list()` returns a `Page<T>` with `total` and `items`, which is a list of envelopes. A `Page` is countable and iterable, and it has `values()` and `hasMore()` helpers.
- `delete()` returns a `DeleteResult` with `key` and `deleted`.

## DTOs or arrays

Write methods accept either a DTO or a plain array that uses the API's snake_case keys:

```php
$apisix->routes()->put('ip', new Route(uri: '/ip', upstreamId: 'httpbin'));
$apisix->routes()->put('ip', ['uri' => '/ip', 'upstream_id' => 'httpbin']);
```

DTOs are `final readonly` classes that mirror the spec schemas:

- **Properties** are camelCase. Spec enums are backed PHP enums. Nested schemas are nested DTOs.
- **`toArray()`** returns the snake_case array.
- **`json_encode($dto)`** keeps empty JSON objects as `{}`, which matters for `plugins`.
- **`with(...)`** returns a modified copy: `$route->with(desc: 'new')`.
- **Read-only fields** such as `create_time` and `update_time` are dropped from request bodies.
- **Unknown keys** returned by the server are kept in `$dto->extra`, so a get → modify → put round trip loses nothing.

When you pass a plain array, an empty object must be written as `new stdClass()` (or `(object) []`). For example, `['plugins' => ['prometheus' => new stdClass()]]`.

## Updating

```php
// PATCH with a JSON merge patch: keys you pass are replaced, and null removes a key.
$apisix->routes()->patch('ip', ['desc' => 'public', 'labels' => null]);

// PATCH a single nested value, given as a slash-separated path.
$apisix->routes()->patchPath('ip', 'plugins/limit-count', ['count' => 10, 'time_window' => 60]);

// Write methods take an optional TTL in seconds.
$apisix->routes()->put('tmp', $route, ttl: 300);

// Force-delete a resource that is still referenced.
$apisix->upstreams()->delete('httpbin', force: true);
```

## Listing and pagination

```php
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;

// One page. Without page or pageSize, APISIX returns everything.
$page = $apisix->routes()->list(new ListQuery(page: 1, pageSize: 50, label: 'team'));

// Lazy: one request per page, as you iterate.
foreach ($apisix->routes()->lazy(new ListQuery(pageSize: 100)) as $envelope) {
    echo $envelope->id(), ' ', $envelope->value->uri, PHP_EOL;
}
```

`ListQuery` supports `page`, `pageSize` (10–500), `name` (a regex), `label` (a label key; resources that carry that key match, whatever its value), `uri` (a regex) and `filter`. If you set a filter that the endpoint's spec does not declare, an `InvalidArgumentException` is thrown. It is never silently ignored.

## Consumers, credentials and secrets

```php
use Aybarsm\Apache\Apisix\AdminApi\Dto\Consumer;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Credential;
use Aybarsm\Apache\Apisix\AdminApi\Dto\VaultSecret;

$apisix->consumers()->put(new Consumer(username: 'jack'));
$apisix->consumers()->credentials('jack')->put('primary', new Credential(
    plugins: ['key-auth' => ['key' => 'secret-key']],
));

$apisix->secrets()->vault()->put('main', new VaultSecret(
    uri: 'https://vault.internal:8200', prefix: 'kv/apisix', token: '...',
));

foreach ($apisix->secrets()->lazy() as $secret) {
    // $secret->value is a VaultSecret, AwsSecret or GcpSecret, depending on its key
}
```

## Plugins, schemas and standalone mode

```php
use Aybarsm\Apache\Apisix\AdminApi\Enums\ResourceKind;
use Aybarsm\Apache\Apisix\AdminApi\Enums\Subsystem;

$apisix->plugins()->names(Subsystem::Stream);        // list<string>
$apisix->plugins()->attributes()['limit-count'];     // PluginAttributes (version, priority, schema, ...)
$apisix->schema()->validate(ResourceKind::Routes, $route); // throws BadRequestException when invalid

// API-driven standalone mode only
$result = $apisix->standalone()->put($config, digest: 'v42', wait: 5000); // StandaloneUpdate::Synced|Accepted|Unchanged
$errors = $apisix->standalone()->validate($config);                      // list<ConfigValidationError>
```

## Errors

```
ApacheApisixApiException (abstract)
├── InvalidArgumentException       rejected before sending (bad id, page size, filter, ...)
├── HydrationException             data did not match the DTO; ->path, e.g. "Route.upstream.timeout.connect"
├── UnexpectedResponseException    a success response that could not be decoded
├── TransportException (abstract)  ->method, ->uri
│   ├── ConnectionException        network failure, DNS or timeout
│   └── RequestException           any other PSR-18 failure
└── ApiException (abstract)        ->status, ->errorMsg, ->description, ->method, ->uri, ->rawBody
    ├── BadRequestException 400    ├── UnauthorizedException 401   ├── ForbiddenException 403
    ├── NotFoundException 404      ├── ConflictException 409       ├── ServerException 5xx
    └── UnexpectedStatusException  any other status
```

The admin key is sent only in the `X-API-KEY` header. It never appears in exception messages or URIs.

## Configuration and custom HTTP clients

```php
use Aybarsm\Apache\Apisix\AdminApi\ApiClient;
use Aybarsm\Apache\Apisix\AdminApi\ClientConfig;

$apisix = new ApiClient(
    new ClientConfig(
        baseUri: 'https://apisix-admin.internal:9180',
        apiKey: $key,
        timeout: 10.0,        // these two apply to the default Guzzle client only
        connectTimeout: 2.0,
        headers: ['X-Request-Source' => 'deployer'],
    ),
    http: $anyPsr18Client,           // optional
    requestFactory: $psr17Factory,   // optional
    streamFactory: $psr17Factory,    // optional
);
```

## Development

```bash
composer test                  # Pest unit + arch suites and PHPStan (level max)
scripts/test-matrix.sh         # the same on PHP 8.3, 8.4 and 8.5
APISIX_ADMIN_KEY=... composer test:integration   # against a live APISIX (APISIX_ADMIN_URL)
composer spec ops              # spec operations and their client mapping
composer spec coverage         # fails unless every spec operation is mapped
composer spec diff 3.19.0 3.20.0  # upgrade report between spec versions
composer spec scaffold dto Route  # generate DTOs, enums and resources from the spec
```

See [CLAUDE.md](CLAUDE.md) for the architecture and conventions. To add a resource or upgrade to a new APISIX spec, follow the workflows in [`.claude/skills/apache-apisix-admin-api-php-client/`](.claude/skills/apache-apisix-admin-api-php-client/).

## License

MIT
