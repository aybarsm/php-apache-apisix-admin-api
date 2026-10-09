# Workflow: add a resource or operation

Use this workflow when `php bin/spec coverage` lists UNMAPPED operations, or when an excluded operation should become supported. Complete each step's check before moving on.

## 1. Understand the operations

```bash
php bin/spec coverage
php bin/spec ops --tag="<Tag>"
jq '.paths["/apisix/admin/<path>"]' resources/apache-apisix/v<latest>.json
```

Note the following for each operation:
- method and path
- path, query and header parameters (`ttl`, `force`, `page`/`page_size`, filters)
- the request body schema
- the response schema for each status

Look for anything the spec cannot express correctly:
- a malformed operationId
- an untyped or missing response body
- a response that contradicts its example
- a parameter whose values look wrong

**Check:** you can name the canonical id, verb and return type of every operation in the tag.

## 2. Verify the behaviour, and record overrides

For each suspicious point:

1. Read the handler source: `container exec apache-apisix sh -c 'sed -n "/^function _M:get/,/^end/p" /usr/local/apisix/apisix/admin/resource.lua'`. The tag-specific modules (`routes.lua`, `consumers.lua`, ...) define `unsupported_methods`, checkers and key builders.
2. If needed, probe the live API with curl, reading the key from `.env` into a shell variable without printing it. Clean up anything you create.
3. Add an entry to `resources/apache-apisix/v<latest>.overrides.json`:
   - `operations.<specId>` for a `rename` or a `responses` replacement
   - `schemas.<Schema>` for missing properties
   - `exclude.<specId>` with a reason, if the operation is out of scope
   - `notes` for behaviour the client absorbs without a schema change

**Check:** `php bin/spec ops --tag="<Tag>"` still loads. It validates the overrides, rejects unknown keys, and errors on stale operationIds or schema names.

## 3. Generate the DTOs and enums

```bash
php bin/spec scaffold dto <ItemSchema> --deep          # preview first
php bin/spec scaffold dto <ItemSchema> --deep --write
grep -rn "TODO" src/Dto src/Enums
```

The generator follows these rules:
- **Names.** Classes get PHP names: `SSL` becomes `Ssl`, `UpstreamTLS` becomes `UpstreamTls`. `Labels` becomes `array<string,string>` with an object key. `Plugins` becomes `array<string,array<string,mixed>>` with an object key. `ResourceId` becomes `string|int`.
- **Enums.** It reuses existing enums that match by name or by value set. Integer cases are named from `x-enumDescriptions`.
- **Unions.** `anyOf`/`oneOf` come out as `mixed // TODO`.

Replace each TODO by hand with a precise type and a private static hydrator. Use `Upstream::nodes()` and `ConfigValidationError::resourceId()` as examples. If two generated enums are identical, merge them into one shared enum and point the DTOs at it.

**Check:** `vendor/bin/pest tests/Spec/DtoKeysTest.php tests/Unit/Dto` passes. That confirms `KEYS` match the spec properties, constructor parameters match `KEYS`, `READ_ONLY` matches `readOnly`, and every spec example round-trips.

## 4. Generate or write the resource

```bash
php bin/spec scaffold resource "<Tag>" --class=<PluralName>          # preview
php bin/spec scaffold resource "<Tag>" --class=<PluralName> --write
```

- **Standard etcd CRUD.** Collection `GET`/`POST`, item `GET`/`PUT`/`PATCH`/`DELETE` and `{id}/{sub_path}` `PATCH` become `list`/`lazy`/`get`/`create`/`put`/`patch`/`patchPath`/`delete` on `AbstractResource<T>`. Usually no edits are needed. Add the class to `UNEDITED_RESOURCES` in `tests/Unit/Dev/ScaffoldTest.php`.
- **Anything else** comes out as a TODO stub mapped to its operationId. Hand-write these, and use the existing resources as patterns:
  - Collection `PUT` with the id in the body: `Consumers::put()`.
  - Nested path parameters: `Credentials`. Use a constructor with an `@internal` docblock, a validated parent identifier, and the accessor `Consumers::credentials()`.
  - A path segment that selects the DTO: `SecretsOfType::dtoClassFor()`.
  - A heterogeneous list: `Secrets::list()`.
  - Non-CRUD endpoints extend `Endpoint` and return DTOs, typed arrays, strings or enums, never PSR-7 types: `Plugins`, `Schema`, `Standalone`.
  - Identifiers that are not ResourceIds: override `itemPath()` and validate with `ResourceId::assertUsername()` or `assertName()`.

Wire the new resource into `ApiClient` with a short accessor that returns `new X($this->transport)`.

**Check:** `php bin/spec coverage` prints `Coverage complete.`, and `vendor/bin/phpstan analyse` is clean.

## 5. Tests

- **Unit, standard CRUD:** add a row to the `crud resources` dataset in `tests/Unit/Resources/CrudResourcesTest.php`. The row holds the accessor, path, minimal value and DTO class.
- **Unit, anything else:** write a focused test under `tests/Unit/Resources/` using `fakeHttp()`/`fakeClient()`. Assert the method, the target (`lastTarget()`), the body (`lastJson()`) and the hydrated types. Use spec examples as fixtures via `SpecExamples::response('<opId>', '<status>')`.
- **Live:** for standard CRUD, add a row to `live crud resources` in `tests/Integration/CrudLifecycleTest.php`. Otherwise write a live test that:
  - uses `liveClient()` and a `liveId()`
  - labels its fixtures `suite=apisix-php`
  - cleans up in `finally`
  - calls `markTestSkipped()` with the APISIX error when the environment lacks a feature
- **README:** document the resource in the resources table.

**Check:** these all pass:
- `scripts/test-matrix.sh`
- `composer test:integration`
- `php scripts/cleanup-integration.php --dry-run`, which reports 0 leftovers

## 6. Commit

```bash
git add -A && git commit -m "Add <Tag> resource" && git push
```

Tag the commit when it completes a milestone:
- before v0.1.0, a patch bump on `v0.0.x`
- after v0.1.0, a minor bump for a new resource and a patch bump for a fix

```bash
git tag -a vX.Y.Z -m "..." && git push origin vX.Y.Z
```
