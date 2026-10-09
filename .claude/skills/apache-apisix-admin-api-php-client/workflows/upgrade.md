# Workflow: upgrade to a new APISIX spec version

Use this workflow when a new pristine spec (`resources/apache-apisix/v<new>.json`) arrives. The client always targets the **latest** spec file.

## 1. Add the spec and prepare its overrides

1. Put the new spec at `resources/apache-apisix/v<new>.json`. Do not modify it.
2. Copy the previous overrides as a starting point: `cp v<old>.overrides.json v<new>.overrides.json`. Then set `"spec": "v<new>.json"`.
3. If the live container is to be upgraded too, confirm `curl -sI http://apache-apisix.internal:9180/apisix/admin | grep Server` reports `APISIX/<new>`.

**Check:** `php bin/spec ops --spec=<new>` loads. A loading error that names an operationId or schema means an override is now stale. Remove or adjust that entry in step 3.

## 2. Read the diff

```bash
php bin/spec diff <old> <new> > /tmp/apisix-diff.md      # Markdown; add --json for tooling
```

The report lists:
- **Operations added**, which you must implement (step 4) or exclude with a reason.
- **Operations removed**, plus *Impact: mapped methods whose operation was removed*. These are breaking changes: delete or deprecate the methods.
- **Operations changed**: endpoint, parameters (added, removed, required, type), request body and response schemas.
- **Schemas added, removed or changed**: properties, enums, required fields and unions.
- **Impact: DTOs to regenerate or review.**
- **Impact: overrides to re-check.**

**Check:** every item in the report has a planned action.

## 3. Re-verify every override

For each override, whether or not the diff lists it:
- **Spec fixed?** If the new spec now says what the override says, delete the override. The round-trip tests will then use the spec's examples again.
- **Still wrong?** Confirm it against the new APISIX source or the live API, and update `evidence` with the version and date.
- **Exclusions:** confirm the reasons in `exclude` still hold.

**Check:** `php bin/spec ops --spec=<new>` loads, and the counts of excluded and unmapped operations make sense.

## 4. Apply the changes to the code

Work through the report:

1. **Changed schemas, with a DTO listed under Impact.**
   - If the file is in `UNEDITED_DTOS`, delete it and run `php bin/spec scaffold dto <Schema> --write`.
   - Otherwise, preview it (`php bin/spec scaffold dto <Schema>`) and port the change into the hand-edited file.
   - Add new enum values to the existing backed enums.
2. **New nested schemas:** run `php bin/spec scaffold dto <Schema> --deep --write`.
3. **Changed operations.** Update the method signature, for example a new filter in `LIST_PARAMS`, a new `ttl`/`force` parameter, or a new return type. `LIST_PARAMS` must equal the spec's query parameters.
4. **Added operations and tags:** follow [add-resource.md](add-resource.md).
5. **Removed operations:** remove the methods, accessors and tests, and note the break in the commit message.
6. **README:** update the version mentioned at the top and the resources table.

**Check:** `php bin/spec coverage` prints `Coverage complete.`, and `vendor/bin/phpstan analyse` is clean.

## 5. Verify

```bash
scripts/test-matrix.sh                       # PHP 8.3/8.4/8.5 unit+arch+PHPStan
composer test:integration                    # against the upgraded live APISIX
php scripts/cleanup-integration.php --dry-run
php bin/spec diff <new> <new>                # sanity: "No structural changes."
```

These tests catch the following automatically:
- `DtoKeysTest` catches DTOs that drift from the spec.
- `SpecExampleRoundTripTest` catches new examples that a DTO cannot hydrate.
- `ScaffoldTest` catches generator output that drifts from files marked as unedited.
- `CrudResourcesTest` checks that each resource still exposes exactly the verbs its spec defines.

If a live test fails while unit tests pass, the server disagrees with the spec. Verify the behaviour and record an override (step 3) instead of bending the code silently.

## 6. Commit and release

```bash
git add -A && git commit -m "Upgrade to APISIX Admin API v<new>" && git push
git tag -a vX.Y.0 -m "Support APISIX v<new>" && git push origin vX.Y.0
```

Use a minor bump for a new spec version. If the diff removed mapped operations, say so in the tag message, because that breaks the public API.

You can keep the old `v<old>.json` and its overrides file for future diffs. The client and its tests always use the latest version.
