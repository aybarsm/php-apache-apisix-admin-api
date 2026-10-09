# CLAUDE.md

This package is `aybarsm/apache-apisix-admin-api`, a typed, resource-oriented PHP client for the Apache APISIX Admin REST API. Its namespace is `Aybarsm\Apache\Apisix\AdminApi\`.

## Source of truth

- **The spec.** The only source of truth is `resources/apache-apisix/v{version}.json`, the pristine OpenAPI spec. The latest file is the target. Never edit a spec file.
- **Overrides.** Known spec bugs live next to the spec in `v{version}.overrides.json`, which is validated against `overrides.schema.json`. It has these sections:
  - `operations`: keyed by the spec's original operationId. An entry can hold a `rename` (giving the canonical id) and/or `responses` (replacement schemas per status).
  - `schemas`: properties added to a component schema.
  - `exclude`: operations that are intentionally not implemented, each with a reason.
  - `notes`: free-form observations.
- **Evidence.** Every override needs `issue`, `actual` and `evidence`. Evidence is the APISIX source (read with `container exec apache-apisix ...`), a live request, or the spec contradicting itself. Never record a quirk you have not verified.
- **Mapping.** Each public client method maps to exactly one canonical operationId with `#[SpecOperation('...')]`. Convenience methods such as `lazy()` carry no attribute.

## Commands

```bash
composer test                          # unit + arch suites (Pest 4) and PHPStan level max
scripts/test-matrix.sh                 # unit/arch/PHPStan on PHP 8.3, 8.4, 8.5 (composer update per version)
MATRIX_INTEGRATION=1 scripts/test-matrix.sh   # ...plus the live suite
composer test:integration              # live APISIX; needs APISIX_ADMIN_KEY (env or gitignored .env)
composer test:integration:cleanup      # delete leftover it-* / suite=apisix-php fixtures
php bin/spec ops [--tag=Routes] [--json]      # operations + mapping/EXCLUDED/UNMAPPED
php bin/spec coverage                  # exit 1 unless all non-excluded ops are mapped exactly once
php bin/spec diff <from> [<to>] [--json]      # upgrade report between pristine spec versions
php bin/spec scaffold dto <Schema> [--deep] [--write]
php bin/spec scaffold resource "<Tag>" [--class=Name] [--write]   # prints unless --write; never overwrites
```

The PHP binaries are at `/opt/homebrew/opt/php@{8.3,8.4,8.5}/bin/php`. The live APISIX is the `apache-apisix` container, reached at `http://apache-apisix.internal:9180` (set by `APISIX_ADMIN_URL`). It runs in etcd mode with stream mode disabled, so the stream-route and standalone live tests are skipped, each with its reason.

## Architecture

```
ApiClient (factory)  ->  Resources\*  ->  Internal\Transport  ->  PSR-18 client (Guzzle by default)
                              |                 |
                          Dto\* (readonly)   Internal\Response (never public)
                          Support\* (Envelope<T>, Page<T>, ListQuery, DeleteResult, ResourceId, StandaloneSnapshot)
```

- **`ApiClient`** is a final readonly class. It creates one `Transport` and returns a new resource object from each accessor (`routes()`, `consumers()->credentials($u)`, `secrets()->vault()`, ...).
- **`Internal\Transport`** is the only place where HTTP happens. It:
  - prefixes `/apisix/admin`
  - URL-encodes each path segment
  - sends the `X-API-KEY` header (and only that header)
  - encodes JSON with flags that keep `{}` as `{}`
  - decodes leniently (an undecodable body stays as a raw string, and `object()` throws later if an object was required)
  - maps HTTP status codes to exceptions through `ExceptionFactory`
- **`Resources\Endpoint`** holds the transport. Endpoints that are not CRUD extend it: `Plugins`, `Schema`, `Standalone`, `Secrets`.
- **`Resources\AbstractResource<T>`** handles etcd-backed CRUD. Resources call its protected `doList`, `doLazy`, `doGet`, `doCreate`, `doPut`, `doPatch`, `doPatchPath` and `doDelete` from thin public methods.
- **`Internal\Pagination::lazy()`** is the shared lazy generator. It stops on an empty page, a short page, or once `total` items have been seen. If the spec declares no `page`/`page_size` for an endpoint, it fetches a single time.
- **DTOs** (`Dto\*`) are `final readonly` classes that `implements Dto` and `use DtoBehaviour`.
  - Promoted camelCase properties cover every schema key, plus `array $extra` for unknown keys.
  - `fromData(Data)` uses the internal `Data` accessors, which raise path-aware `HydrationException`s.
  - `payload()` maps JSON keys to values.
  - Typed constants: `SCHEMA`, `KEYS` (in spec order), `READ_ONLY` (spec `readOnly`, stripped by `toRequest()`) and `OBJECT_KEYS` (key => depth of maps that must encode as `{}`).
- **Exceptions** all extend `Exceptions\ApacheApisixApiException`. They split into `ApiException` (one subclass per status), `TransportException` (with `ConnectionException` and `RequestException`), `UnexpectedResponseException`, `HydrationException` and `InvalidArgumentException`.

## Conventions (enforced by tests/Arch and PHPStan)

- **Strict types and the PHP 8.3 floor.** `declare(strict_types=1);` goes in every PHP file. PHPStan runs at level max with `phpVersion: 80300`. Do not use PHP 8.4+ features: property hooks, asymmetric visibility, `new Foo()->bar()` without parentheses, `array_find`, `array_any`.
- **Class modifiers.** Every concrete class is `final`. Classes in Dto, Support and Attributes are `readonly`. Enums are backed. Every class constant is typed. Every overriding or implementing method has `#[Override]`.
- **Public signatures.** No public signature may mention PSR-7 messages or `Internal\Response`/`Transport`. Wiring constructors are exempt but must be tagged `@internal`.
- **Write inputs.** Write methods accept `Dto|array`. Arrays use the API's snake_case keys and pass through unchanged. `patch()` takes an `array` merge patch, where `null` removes a key.
- **Naming.**
  - Generated names come from `Dev\Spec\Scaffold\Naming` (for example `UpstreamTLS` becomes `UpstreamTls`, and `SSL` becomes `Ssl`).
  - Integer enum cases take their names from the spec's `x-enumDescriptions`.
  - The generator reuses an existing enum when one has the same name or the same set of values. `HealthCheckType` and `Status` exist because of this.
- **Generated versus hand-edited files.** `tests/Unit/Dev/ScaffoldTest.php` lists the files that must match generator output byte-for-byte (`UNEDITED_DTOS` and `UNEDITED_RESOURCES`). Remove a file from those lists when you hand-edit it. The hand-edited files today are:
  - `Upstream` (the `nodes` union)
  - `ConfigValidationError` (`resource_id`)
  - `Consumers`, `Credentials`, `Secrets`, `SecretsOfType`, `PluginMetadata`, `Plugins`, `Schema` and `Standalone`
- **Typing of untyped responses.** Spec responses that are untyped or inline get a small DTO with `SCHEMA = ''` (for example `PluginMetadata` and `PluginAttributes`) or a typed array.

## Tests

- **`tests/Unit`.** Uses `FakeHttpClient`, a PSR-18 fake that queues responses and records requests. Fixtures come from the spec's own examples through `Tests\Support\SpecExamples`.
  - `Dto/SpecExampleRoundTripTest` picks up every `*Envelope` and `*ListEnvelope` response example automatically. It skips responses that an override replaces.
  - `Resources/CrudResourcesTest` is dataset-driven. Add each new CRUD resource to it.
- **`tests/Spec`.**
  - `CoverageTest` checks mapping consistency and requires 100% coverage.
  - `DtoKeysTest` checks that `KEYS` match the schema, that constructor parameters match `KEYS`, and that `READ_ONLY` matches the spec's `readOnly`.
- **`tests/Arch`.** Pest arch tests, plus reflection checks for the conventions above.
- **`tests/Integration`.** Runs against live APISIX.
  - IDs come from `liveId()` (`it-xxxxxxxx`) and fixtures carry the label `suite=apisix-php`. Always clean up in `finally`.
  - Filter lists by label key (`label: 'suite'`). `key:value` never matches.

## Commit and release policy

- Work only on `main`. Commit with `git add -A` and a meaningful message, then push at green milestones.
- Tags are annotated and pushed. Each feature milestone gets a patch bump on the `v0.0.x` line, and fixes get patch bumps too.
- `v0.1.0` marks full spec coverage, a green live suite, and complete docs and skill. Bump the minor version after that for new spec versions or resources.
- Never commit `.env`, which holds the admin key.

## Workflows

Use the project skill `.claude/skills/apache-apisix-admin-api-php-client/`:

- `workflows/add-resource.md` covers implementing a spec tag or operation that is not mapped yet.
- `workflows/upgrade.md` covers moving to a new `resources/apache-apisix/v{version}.json`.
