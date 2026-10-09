<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Mapping;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Operation;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Parameter;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Spec;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\Naming;

/**
 * Structural difference between two pristine spec versions, plus its impact on
 * this codebase (mapped methods, DTOs, overrides). Drives the upgrade workflow.
 */
final readonly class SpecDiff
{
    /**
     * @param list<string>                         $addedOperations
     * @param list<string>                         $removedOperations
     * @param array<string, list<string>>          $changedOperations operationId => human-readable changes
     * @param list<string>                         $addedSchemas
     * @param list<string>                         $removedSchemas
     * @param array<string, list<string>>          $changedSchemas    schema => human-readable changes
     * @param list<string>                         $brokenMappings    `Class::method (operationId)` mapped to removed operations
     * @param list<string>                         $affectedDtos      DTO classes whose schema changed or was removed
     * @param array<string, string>                $overridesToRecheck override key => reason
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $addedOperations,
        public array $removedOperations,
        public array $changedOperations,
        public array $addedSchemas,
        public array $removedSchemas,
        public array $changedSchemas,
        public array $brokenMappings,
        public array $affectedDtos,
        public array $overridesToRecheck,
    ) {}

    /**
     * @param list<Mapping> $mappings current `#[SpecOperation]` mappings
     * @param string        $dtoDir   directory holding DTO classes (src/Dto)
     */
    public static function compare(Spec $from, Spec $to, Overrides $overrides, array $mappings = [], string $dtoDir = ''): self
    {
        $fromOps = $from->operations;
        $toOps = $to->operations;

        $added = array_values(array_diff(array_keys($toOps), array_keys($fromOps)));
        $removed = array_values(array_diff(array_keys($fromOps), array_keys($toOps)));
        sort($added);
        sort($removed);

        $changedOps = [];
        foreach (array_intersect_key($fromOps, $toOps) as $id => $before) {
            $changes = self::operationChanges($from, $before, $to, $toOps[$id]);
            if ($changes !== []) {
                $changedOps[$id] = $changes;
            }
        }
        ksort($changedOps);

        $fromSchemas = $from->schemas();
        $toSchemas = $to->schemas();
        $addedSchemas = array_values(array_diff(array_keys($toSchemas), array_keys($fromSchemas)));
        $removedSchemas = array_values(array_diff(array_keys($fromSchemas), array_keys($toSchemas)));
        sort($addedSchemas);
        sort($removedSchemas);

        $changedSchemas = [];
        foreach (array_intersect_key($fromSchemas, $toSchemas) as $name => $before) {
            $changes = self::schemaChanges($from, $before, $to, $toSchemas[$name]);
            if ($changes !== []) {
                $changedSchemas[$name] = $changes;
            }
        }
        ksort($changedSchemas);

        $broken = [];
        foreach ($mappings as $mapping) {
            if (in_array($mapping->operationId, $removed, true)) {
                $broken[] = sprintf('%s (%s)', $mapping->target(), $mapping->operationId);
            }
        }

        $affectedDtos = [];
        foreach ([...array_keys($changedSchemas), ...$removedSchemas] as $schema) {
            $file = rtrim($dtoDir, '/').'/'.Naming::className($schema).'.php';
            if ($dtoDir !== '' && is_file($file)) {
                $affectedDtos[] = Naming::className($schema);
            }
        }
        sort($affectedDtos);

        $recheck = [];
        foreach (array_keys($overrides->operations + $overrides->exclude) as $specId) {
            if (in_array($specId, $removed, true)) {
                $recheck[$specId] = 'operation no longer exists in v'.$to->version;
            } elseif (isset($changedOps[$specId])) {
                $recheck[$specId] = 'operation changed in v'.$to->version;
            }
        }
        foreach (array_keys($overrides->schemas) as $schema) {
            if (in_array($schema, $removedSchemas, true)) {
                $recheck['schema:'.$schema] = 'schema no longer exists in v'.$to->version;
            } elseif (isset($changedSchemas[$schema])) {
                $recheck['schema:'.$schema] = 'schema changed in v'.$to->version;
            }
        }
        ksort($recheck);

        return new self($from->version, $to->version, $added, $removed, $changedOps, $addedSchemas, $removedSchemas, $changedSchemas, $broken, $affectedDtos, $recheck);
    }

    public function isEmpty(): bool
    {
        return $this->addedOperations === [] && $this->removedOperations === [] && $this->changedOperations === []
            && $this->addedSchemas === [] && $this->removedSchemas === [] && $this->changedSchemas === [];
    }

    /**
     * @return list<string>
     */
    private static function operationChanges(Spec $fromSpec, Operation $from, Spec $toSpec, Operation $to): array
    {
        $changes = [];
        if ($from->method !== $to->method || $from->path !== $to->path) {
            $changes[] = sprintf('endpoint: %s %s -> %s %s', $from->method, $from->path, $to->method, $to->path);
        }

        $fromParams = self::params($from);
        $toParams = self::params($to);
        foreach (array_diff_key($toParams, $fromParams) as $key => $param) {
            $changes[] = sprintf('param added: %s%s', $key, $param->required ? ' (required)' : '');
        }
        foreach (array_keys(array_diff_key($fromParams, $toParams)) as $key) {
            $changes[] = 'param removed: '.$key;
        }
        foreach (array_intersect_key($fromParams, $toParams) as $key => $before) {
            $after = $toParams[$key];
            if ($before->required !== $after->required) {
                $changes[] = sprintf('param %s: required %s -> %s', $key, var_export($before->required, true), var_export($after->required, true));
            }
            $a = self::signature($fromSpec, $before->schema);
            $b = self::signature($toSpec, $after->schema);
            if ($a !== $b) {
                $changes[] = sprintf('param %s: %s -> %s', $key, $a, $b);
            }
        }

        $a = self::signature($fromSpec, $from->requestSchema ?? []);
        $b = self::signature($toSpec, $to->requestSchema ?? []);
        if ($a !== $b) {
            $changes[] = sprintf('request body: %s -> %s', $a, $b);
        }

        $statuses = array_unique([...array_map('strval', array_keys($from->responses)), ...array_map('strval', array_keys($to->responses))]);
        sort($statuses);
        foreach ($statuses as $status) {
            $hasBefore = array_key_exists($status, $from->responses);
            $hasAfter = array_key_exists($status, $to->responses);
            if (! $hasBefore || ! $hasAfter) {
                $changes[] = sprintf('response %s %s', $status, $hasAfter ? 'added' : 'removed');
                continue;
            }
            $a = self::signature($fromSpec, $from->responses[$status] ?? []);
            $b = self::signature($toSpec, $to->responses[$status] ?? []);
            if ($a !== $b) {
                $changes[] = sprintf('response %s: %s -> %s', $status, $a, $b);
            }
        }

        return $changes;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     *
     * @return list<string>
     */
    private static function schemaChanges(Spec $fromSpec, array $from, Spec $toSpec, array $to): array
    {
        $changes = [];
        $before = Json::optionalMap($from['properties'] ?? null, 'properties');
        $after = Json::optionalMap($to['properties'] ?? null, 'properties');

        foreach (array_diff_key($after, $before) as $key => $schema) {
            $changes[] = sprintf('property added: %s %s', $key, self::signature($toSpec, Json::optionalMap($schema, $key)));
        }
        foreach (array_keys(array_diff_key($before, $after)) as $key) {
            $changes[] = 'property removed: '.$key;
        }
        foreach (array_intersect_key($before, $after) as $key => $schema) {
            $a = self::signature($fromSpec, Json::optionalMap($schema, $key));
            $b = self::signature($toSpec, Json::optionalMap($after[$key], $key));
            if ($a !== $b) {
                $changes[] = sprintf('property %s: %s -> %s', $key, $a, $b);
            }
        }

        $reqBefore = array_values(array_filter(is_array($from['required'] ?? null) ? $from['required'] : [], is_string(...)));
        $reqAfter = array_values(array_filter(is_array($to['required'] ?? null) ? $to['required'] : [], is_string(...)));
        foreach (array_diff($reqAfter, $reqBefore) as $key) {
            $changes[] = 'now required: '.$key;
        }
        foreach (array_diff($reqBefore, $reqAfter) as $key) {
            $changes[] = 'no longer required: '.$key;
        }

        foreach (['anyOf', 'oneOf', 'allOf', 'enum', 'type'] as $keyword) {
            $a = json_encode($from[$keyword] ?? null);
            $b = json_encode($to[$keyword] ?? null);
            if ($a !== $b) {
                $changes[] = sprintf('%s: %s -> %s', $keyword, $a, $b);
            }
        }

        return $changes;
    }

    /**
     * @return array<string, Parameter>
     */
    private static function params(Operation $op): array
    {
        $out = [];
        foreach ($op->parameters as $param) {
            $out[$param->in.':'.$param->name] = $param;
        }

        return $out;
    }

    /**
     * Compact type description used to detect meaningful schema changes
     * (descriptions and examples are ignored).
     *
     * @param array<string, mixed> $schema
     */
    private static function signature(Spec $spec, array $schema): string
    {
        if ($schema === []) {
            return '(none)';
        }
        if (isset($schema['allOf']) && is_array($schema['allOf']) && count($schema['allOf']) === 1 && is_array($schema['allOf'][0])) {
            $schema = Json::optionalMap($schema['allOf'][0], 'allOf');
        }
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return '$'.RefResolver::refName($schema['$ref']);
        }

        $parts = [];
        $type = $schema['type'] ?? null;
        $parts[] = is_array($type) ? implode('|', array_filter($type, is_string(...))) : (is_string($type) ? $type : 'any');
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $parts[] = 'enum'.json_encode($schema['enum']);
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $parts[] = 'of '.self::signature($spec, Json::optionalMap($schema['items'], 'items'));
        }
        foreach (['anyOf', 'oneOf'] as $keyword) {
            if (isset($schema[$keyword]) && is_array($schema[$keyword])) {
                $parts[] = $keyword.'['.implode(', ', array_map(
                    static fn (mixed $s): string => self::signature($spec, Json::optionalMap($s, $keyword)),
                    $schema[$keyword],
                )).']';
            }
        }
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $keys = array_map(strval(...), array_keys($schema['properties']));
            sort($keys);
            $parts[] = '{'.implode(',', $keys).'}';
        }

        return implode(' ', $parts);
    }
}
