---
name: apache-apisix-admin-api-php-client
description: Maintain the aybarsm/apache-apisix-admin-api PHP client. Use when adding or changing a resource or Admin API operation, upgrading to a new APISIX spec version (resources/apache-apisix/v{version}.json), recording a spec quirk in the overrides file, or regenerating DTOs/enums/resources with bin/spec. Covers the spec-only source-of-truth rules, the bin/spec tooling, and the add-resource and upgrade workflows.
---

# APISIX Admin API PHP client

The client is generated and checked against **one source of truth**: the pristine OpenAPI spec `resources/apache-apisix/v{version}.json`, plus the evidence-backed corrections in `v{version}.overrides.json`. Code never invents API behaviour that the spec, an override, or a verified APISIX source/live observation does not support.

Read `CLAUDE.md` first for the architecture, conventions and commands.

## Pick the workflow

| Situation | Workflow |
|---|---|
| A spec tag or operation is UNMAPPED (`php bin/spec coverage` fails), or an excluded operation should now be supported | [workflows/add-resource.md](workflows/add-resource.md) |
| A new `resources/apache-apisix/v{version}.json` has been added | [workflows/upgrade.md](workflows/upgrade.md) |
| The live API disagrees with the spec | Record it as an override (below), then continue with add-resource |

## Non-negotiables

1. **Never edit `v{version}.json`.** Corrections go in `v{version}.overrides.json`.
2. **Every override needs evidence**, in its `issue` / `actual` / `evidence` fields:
   - APISIX source, read inside the container with `container exec apache-apisix sh -c 'sed -n ... /usr/local/apisix/apisix/admin/<file>.lua'`
   - a live request against `http://apache-apisix.internal:9180`, using the key in `.env`; never print or commit the key
   - the spec contradicting itself
3. **Map each public operation method exactly once** with `#[SpecOperation('<canonical id>')]`. The canonical id is the spec operationId, or its override `rename`. Helpers like `lazy()` get no attribute.
4. **Prefer generated code.** Run `bin/spec scaffold` first, then hand-edit only what the generator cannot model:
   - `anyOf`/`oneOf` unions, which it emits as `mixed` plus a TODO
   - nested path params
   - collection `PUT`
   - non-envelope responses

   When you hand-edit a generated file, remove it from the `UNEDITED_*` lists in `tests/Unit/Dev/ScaffoldTest.php`.
5. **Keep the hard constraints:**
   - PHP 8.3 compatibility
   - `declare(strict_types=1)`
   - `final` (and `readonly` for DTOs and support types)
   - typed constants, backed enums, `#[Override]`
   - no PSR-7 types in public signatures
6. **Green before commit:**
   - `scripts/test-matrix.sh` (PHP 8.3/8.4/8.5: Pest unit + arch, PHPStan max)
   - `php bin/spec coverage`
   - `composer test:integration`, when the change touches API behaviour
7. **Commit and tag on `main` only.** Use `git add -A`, a meaningful message, and push. Tag milestones with annotated tags: a patch bump on the `0.0.x` line before v0.1.0, and a minor bump after it for a new spec version.

## Tooling cheat sheet

```bash
php bin/spec ops --tag="<Tag>"                 # what exists, what is mapped
php bin/spec coverage                          # what is missing
php bin/spec diff <old> <new>                  # upgrade report (+ impact on code/overrides)
php bin/spec scaffold dto <Schema> --deep      # preview DTO + nested DTOs + enums
php bin/spec scaffold resource "<Tag>" --class=<Plural>   # preview resource class
# add --write to create missing files (never overwrites)
```

## Override cheat sheet

```jsonc
{
  "operations": {
    "<specOperationId>": {
      "rename": "lowerCamelCanonicalId",                                       // optional
      "responses": { "200": { "$ref": "#/components/schemas/XEnvelope" } },    // optional
      "issue": "what the spec says", "actual": "what APISIX does", "evidence": "source file/function or live request + date"
    }
  },
  "schemas": { "<Schema>": { "properties": { "<key>": { "type": "integer", "readOnly": true } }, "issue": "...", "actual": "...", "evidence": "..." } },
  "exclude": { "<specOperationId>": "reason it is intentionally not implemented" },
  "notes": [ { "topic": "...", "detail": "..." } ]
}
```

An override changes what `bin/spec`, the generators and the tests see:
- `SpecLoader` merges it into the spec.
- A response override switches the round-trip tests from the spec's (wrong) example to the corrected shape.
- A schema override adds properties to the generated DTO and to `DtoKeysTest`.
