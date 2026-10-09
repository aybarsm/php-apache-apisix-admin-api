<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Json;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Spec;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\RefResolver;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecException;

/**
 * Generates readonly DTO (and backed enum) source from a spec schema.
 *
 * Output follows the conventions of src/Dto: promoted properties, `fromData()`
 * via the internal Data accessors, `payload()`, typed SCHEMA/KEYS/READ_ONLY/OBJECT_KEYS.
 * Unions it cannot model are emitted as `mixed` with a TODO for hand-editing.
 */
final readonly class DtoGenerator
{
    public const string DTO_NAMESPACE = 'Aybarsm\\Apache\\Apisix\\AdminApi\\Dto';

    public const string ENUM_NAMESPACE = 'Aybarsm\\Apache\\Apisix\\AdminApi\\Enums';

    /** Schemas that map onto built-in PHP shapes instead of DTOs. */
    private const array SPECIAL = ['ResourceId', 'Labels', 'Plugins'];

    public function __construct(
        private Spec $spec,
    ) {}

    /**
     * @return list<Property>
     */
    public function properties(string $schemaName): array
    {
        $schema = $this->schema($schemaName);
        $required = array_values(array_filter(Json::list($schema['required'] ?? [], $schemaName.'.required'), is_string(...)));
        $out = [];
        foreach (Json::optionalMap($schema['properties'] ?? null, $schemaName.'.properties') as $key => $prop) {
            $out[] = $this->property($schemaName, $key, Json::map($prop, $schemaName.'.'.$key), in_array($key, $required, true));
        }

        return $out;
    }

    /**
     * Nested schemas (and the schema itself) that a DTO for `$schemaName` needs, depth-first.
     *
     * @return list<string>
     */
    public function closure(string $schemaName): array
    {
        $seen = [];
        $walk = function (string $name) use (&$walk, &$seen): void {
            if (isset($seen[$name])) {
                return;
            }
            $seen[$name] = true;
            foreach ($this->properties($name) as $property) {
                if ($property->dependency !== null) {
                    $walk($property->dependency);
                }
            }
        };
        $walk($schemaName);

        return array_keys($seen);
    }

    public function dtoSource(string $schemaName): string
    {
        $schema = $this->schema($schemaName);
        $class = Naming::className($schemaName);
        $properties = $this->properties($schemaName);
        usort($properties, static fn (Property $a, Property $b): int => $b->required <=> $a->required);
        $ordered = $this->properties($schemaName);

        $uses = [
            self::DTO_NAMESPACE.'\\Concerns\\DtoBehaviour',
            self::DTO_NAMESPACE.'\\Contracts\\Dto',
            'Aybarsm\\Apache\\Apisix\\AdminApi\\Internal\\Data',
            'Override',
        ];
        foreach ($properties as $p) {
            if ($p->enum !== null) {
                $uses[] = self::ENUM_NAMESPACE.'\\'.$p->enum['name'];
            }
        }
        $uses = array_values(array_unique($uses));
        sort($uses);

        $description = is_string($schema['description'] ?? null) ? trim(strtok($schema['description'], "\n") ?: '') : '';
        $keys = array_map(static fn (Property $p): string => var_export($p->key, true), $ordered);
        $readOnly = array_map(static fn (Property $p): string => var_export($p->key, true), array_values(array_filter($ordered, static fn (Property $p): bool => $p->readOnly)));
        $objectKeys = array_map(static fn (Property $p): string => sprintf('%s => %d', var_export($p->key, true), $p->objectDepth), array_values(array_filter($ordered, static fn (Property $p): bool => $p->objectDepth > 0)));

        $docParams = [];
        $ctor = [];
        foreach ($properties as $p) {
            if ($p->docType !== null) {
                $docParams[] = sprintf('     * @param %s $%s', $p->docType.($p->required ? '' : '|null'), $p->name);
            }
            $ctor[] = sprintf('        public %s $%s%s,', $p->declaredType(), $p->name, $p->required ? '' : ' = null').($p->todo !== null ? ' // TODO: '.$p->todo : '');
        }
        $docParams[] = '     * @param array<string, mixed> $extra';
        $ctor[] = '        public array $extra = [],';

        $hydrate = array_map(static fn (Property $p): string => sprintf('            %s: %s,', $p->name, $p->hydration()), $properties);
        $hydrate[] = '            extra: $data->extra(self::KEYS),';
        $payload = array_map(static fn (Property $p): string => sprintf('            %s => $this->%s,', var_export($p->key, true), $p->name), $ordered);

        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace '.self::DTO_NAMESPACE.';',
            '',
            ...array_map(static fn (string $u): string => 'use '.$u.';', $uses),
            '',
            '/**',
            sprintf(' * Spec schema `%s`%s', $schemaName, $description === '' ? '.' : ': '.$description),
            ' */',
            sprintf('final readonly class %s implements Dto', $class),
            '{',
            '    use DtoBehaviour;',
            '',
            sprintf("    public const string SCHEMA = '%s';", $schemaName),
            '',
            sprintf('    public const array KEYS = [%s];', implode(', ', $keys)),
        ];
        if ($readOnly !== []) {
            array_push($lines, '', sprintf('    public const array READ_ONLY = [%s];', implode(', ', $readOnly)));
        }
        if ($objectKeys !== []) {
            array_push($lines, '', sprintf('    public const array OBJECT_KEYS = [%s];', implode(', ', $objectKeys)));
        }
        $lines = [
            ...$lines,
            '',
            '    /**',
            ...$docParams,
            '     */',
            '    public function __construct(',
            ...$ctor,
            '    ) {}',
            '',
            '    #[Override]',
            '    public static function fromData(Data $data): static',
            '    {',
            '        return new self(',
            ...$hydrate,
            '        );',
            '    }',
            '',
            '    #[Override]',
            '    protected function payload(): array',
            '    {',
            '        return [',
            ...$payload,
            '        ];',
            '    }',
            '}',
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string> enum class => source
     */
    public function enumSources(string $schemaName): array
    {
        $out = [];
        foreach ($this->properties($schemaName) as $p) {
            if ($p->enum === null) {
                continue;
            }
            $cases = array_map(
                static fn (string|int $v): string => sprintf('    case %s = %s;', Naming::enumCase($v), var_export($v, true)),
                $p->enum['values'],
            );
            $out[$p->enum['name']] = implode("\n", [
                '<?php',
                '',
                'declare(strict_types=1);',
                '',
                'namespace '.self::ENUM_NAMESPACE.';',
                '',
                '/**',
                sprintf(' * Spec `%s.%s` values.', $schemaName, $p->key),
                ' */',
                sprintf('enum %s: %s', $p->enum['name'], $p->enum['backing']),
                '{',
                ...$cases,
                '}',
                '',
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $name): array
    {
        $schema = $this->spec->schemas()[$name] ?? throw new SpecException(sprintf('Unknown schema "%s"', $name));

        return $this->spec->refs->resolve($schema);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function property(string $owner, string $key, array $schema, bool $required): Property
    {
        $name = Naming::property($key);
        $readOnly = ($schema['readOnly'] ?? false) === true;

        if (isset($schema['allOf']) && is_array($schema['allOf']) && count($schema['allOf']) === 1) {
            $schema = Json::map($schema['allOf'][0], $owner.'.'.$key.'.allOf');
        }

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $ref = RefResolver::refName($schema['$ref']);
            if (in_array($ref, self::SPECIAL, true)) {
                return match ($ref) {
                    'ResourceId' => new Property($key, $name, 'string|int', null, '$data->id(%s)', $required, $readOnly),
                    'Labels' => new Property($key, $name, 'array', 'array<string, string>', '$data->stringMap(%s)', $required, $readOnly, 1),
                    default => new Property($key, $name, 'array', 'array<string, array<string, mixed>>', '$data->pluginMap(%s)', $required, $readOnly, 2),
                };
            }

            $target = $this->spec->refs->resolve($schema);
            if (isset($target['properties'])) {
                $class = Naming::className($ref);

                return new Property($key, $name, $class, null, '$data->dto(%s, '.$class.'::class)', $required, $readOnly, 0, $ref);
            }
            $schema = $target;
        }

        if (isset($schema['anyOf']) || isset($schema['oneOf'])) {
            return new Property($key, $name, 'mixed', null, '$data->mixed(%s)', false, $readOnly, todo: 'model the anyOf/oneOf union');
        }

        $type = $schema['type'] ?? null;
        if (is_array($type)) {
            $type = array_values(array_filter($type, static fn (mixed $t): bool => $t !== 'null'))[0] ?? null;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && in_array($type, ['string', 'integer'], true)) {
            $values = array_values(array_filter($schema['enum'], static fn (mixed $v): bool => is_string($v) || is_int($v)));
            $enum = Naming::className($owner).Naming::className($key);

            return new Property($key, $name, $enum, null, '$data->enum(%s, '.$enum.'::class)', $required, $readOnly, enum: [
                'name' => $enum,
                'backing' => $type === 'integer' ? 'int' : 'string',
                'values' => $values,
            ]);
        }

        return match ($type) {
            'string' => new Property($key, $name, 'string', null, '$data->string(%s)', $required, $readOnly),
            'integer' => new Property($key, $name, 'int', null, '$data->int(%s)', $required, $readOnly),
            'number' => new Property($key, $name, 'int|float', null, '$data->number(%s)', $required, $readOnly),
            'boolean' => new Property($key, $name, 'bool', null, '$data->bool(%s)', $required, $readOnly),
            'array' => $this->arrayProperty($owner, $key, $name, $schema, $required, $readOnly),
            'object' => new Property($key, $name, 'array', 'array<string, mixed>', '$data->map(%s)', $required, $readOnly, 1),
            default => new Property($key, $name, 'mixed', null, '$data->mixed(%s)', false, $readOnly, todo: 'untyped in spec'),
        };
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function arrayProperty(string $owner, string $key, string $name, array $schema, bool $required, bool $readOnly): Property
    {
        $items = Json::optionalMap($schema['items'] ?? null, $owner.'.'.$key.'.items');
        if (isset($items['$ref']) && is_string($items['$ref'])) {
            $ref = RefResolver::refName($items['$ref']);
            $target = $this->spec->refs->resolve($items);
            if (isset($target['properties'])) {
                $class = Naming::className($ref);

                return new Property($key, $name, 'array', 'list<'.$class.'>', '$data->dtoList(%s, '.$class.'::class)', $required, $readOnly, 0, $ref);
            }
            $items = $target;
        }

        return match ($items['type'] ?? null) {
            'string' => new Property($key, $name, 'array', 'list<string>', '$data->stringList(%s)', $required, $readOnly),
            'integer' => new Property($key, $name, 'array', 'list<int>', '$data->intList(%s)', $required, $readOnly),
            default => new Property($key, $name, 'array', 'list<mixed>', '$data->list(%s)', $required, $readOnly),
        };
    }
}
