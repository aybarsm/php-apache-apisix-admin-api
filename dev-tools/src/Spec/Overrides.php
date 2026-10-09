<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\OperationOverride;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\SchemaOverride;

/**
 * Versioned corrections layered over a pristine spec (`v{version}.overrides.json`).
 *
 * Structure is documented by `resources/apache-apisix/overrides.schema.json`
 * and enforced by {@see self::fromArray()}.
 */
final readonly class Overrides
{
    private const array TOP_LEVEL_KEYS = ['$schema', 'spec', 'operations', 'schemas', 'exclude', 'notes'];

    private const array SCHEMA_KEYS = ['properties', 'issue', 'actual', 'evidence'];

    private const array OPERATION_KEYS = ['rename', 'responses', 'issue', 'actual', 'evidence'];

    /**
     * @param array<string, OperationOverride>             $operations keyed by original operationId
     * @param array<string, string>                        $exclude    original operationId => reason
     * @param list<array{topic: string, detail: string}>   $notes
     * @param array<string, SchemaOverride>                $schemas    component schema name => patch
     */
    public function __construct(
        public string $spec,
        public array $operations = [],
        public array $exclude = [],
        public array $notes = [],
        public array $schemas = [],
    ) {}

    public static function fromFile(string $path): self
    {
        return self::fromArray(Json::decodeFile($path), basename($path));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $context = 'overrides'): self
    {
        foreach (array_keys($data) as $key) {
            if (! in_array($key, self::TOP_LEVEL_KEYS, true)) {
                throw new SpecException(sprintf('%s: unknown key "%s"', $context, $key));
            }
        }

        foreach (['spec', 'operations', 'exclude', 'notes'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw new SpecException(sprintf('%s: missing required key "%s"', $context, $required));
            }
        }

        $spec = Json::string($data['spec'], $context.'.spec');
        if (preg_match('/^v\d+\.\d+\.\d+\.json$/', $spec) !== 1) {
            throw new SpecException(sprintf('%s.spec: must look like v1.2.3.json', $context));
        }

        $operations = [];
        foreach (Json::map($data['operations'], $context.'.operations') as $id => $entry) {
            $operations[$id] = self::operation($id, Json::map($entry, sprintf('%s.operations.%s', $context, $id)), $context);
        }

        $schemas = [];
        foreach (Json::optionalMap($data['schemas'] ?? null, $context.'.schemas') as $name => $entry) {
            $schemas[$name] = self::schema($name, Json::map($entry, sprintf('%s.schemas.%s', $context, $name)), $context);
        }

        $exclude = [];
        foreach (Json::map($data['exclude'], $context.'.exclude') as $id => $reason) {
            $exclude[$id] = Json::nonEmptyString($reason, sprintf('%s.exclude.%s', $context, $id));
        }

        $notes = [];
        foreach (Json::list($data['notes'], $context.'.notes') as $i => $note) {
            $ctx = sprintf('%s.notes[%d]', $context, $i);
            $note = Json::map($note, $ctx);
            if (array_diff(array_keys($note), ['topic', 'detail']) !== []) {
                throw new SpecException(sprintf('%s: only "topic" and "detail" are allowed', $ctx));
            }
            $notes[] = [
                'topic' => Json::nonEmptyString($note['topic'] ?? null, $ctx.'.topic'),
                'detail' => Json::nonEmptyString($note['detail'] ?? null, $ctx.'.detail'),
            ];
        }

        foreach (array_keys($exclude) as $id) {
            if (isset($operations[$id])) {
                throw new SpecException(sprintf('%s: "%s" is both overridden and excluded', $context, $id));
            }
        }

        return new self($spec, $operations, $exclude, $notes, $schemas);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function schema(string $name, array $entry, string $context): SchemaOverride
    {
        $ctx = sprintf('%s.schemas.%s', $context, $name);
        foreach (array_keys($entry) as $key) {
            if (! in_array($key, self::SCHEMA_KEYS, true)) {
                throw new SpecException(sprintf('%s: unknown key "%s"', $ctx, $key));
            }
        }

        $properties = [];
        foreach (Json::map($entry['properties'] ?? null, $ctx.'.properties') as $property => $schema) {
            $properties[$property] = Json::map($schema, sprintf('%s.properties.%s', $ctx, $property));
        }
        if ($properties === []) {
            throw new SpecException(sprintf('%s.properties: must not be empty', $ctx));
        }

        return new SchemaOverride(
            properties: $properties,
            issue: Json::nonEmptyString($entry['issue'] ?? null, $ctx.'.issue'),
            actual: Json::nonEmptyString($entry['actual'] ?? null, $ctx.'.actual'),
            evidence: Json::nonEmptyString($entry['evidence'] ?? null, $ctx.'.evidence'),
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function operation(string $id, array $entry, string $context): OperationOverride
    {
        $ctx = sprintf('%s.operations.%s', $context, $id);
        foreach (array_keys($entry) as $key) {
            if (! in_array($key, self::OPERATION_KEYS, true)) {
                throw new SpecException(sprintf('%s: unknown key "%s"', $ctx, $key));
            }
        }

        $rename = null;
        if (array_key_exists('rename', $entry)) {
            $rename = Json::string($entry['rename'], $ctx.'.rename');
            if (preg_match('/^[a-z][A-Za-z0-9]*$/', $rename) !== 1) {
                throw new SpecException(sprintf('%s.rename: must be lowerCamelCase', $ctx));
            }
        }

        $responses = [];
        foreach (Json::optionalMap($entry['responses'] ?? null, $ctx.'.responses') as $status => $schema) {
            $status = (string) $status; // numeric JSON keys become ints in PHP arrays
            if (preg_match('/^[1-5]\d{2}$/', $status) !== 1) {
                throw new SpecException(sprintf('%s.responses: invalid status "%s"', $ctx, $status));
            }
            $responses[$status] = Json::map($schema, sprintf('%s.responses.%s', $ctx, $status));
        }

        return new OperationOverride(
            rename: $rename,
            responses: $responses,
            issue: Json::nonEmptyString($entry['issue'] ?? null, $ctx.'.issue'),
            actual: Json::nonEmptyString($entry['actual'] ?? null, $ctx.'.actual'),
            evidence: Json::nonEmptyString($entry['evidence'] ?? null, $ctx.'.evidence'),
        );
    }
}
