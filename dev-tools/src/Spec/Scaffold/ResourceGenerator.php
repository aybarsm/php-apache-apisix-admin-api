<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Operation;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Parameter;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Spec;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\RefResolver;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecException;

/**
 * Generates a resource class for a spec tag, following src/Resources conventions.
 *
 * Standard etcd CRUD shapes become list/lazy/get/create/put/patch/patchPath/delete;
 * anything else is emitted as a TODO stub mapped to its operationId.
 */
final readonly class ResourceGenerator
{
    public const string NAMESPACE = 'Aybarsm\\Apache\\Apisix\\AdminApi\\Resources';

    private const string PREFIX = '/apisix/admin/';

    private const array VERB_ORDER = ['list', 'get', 'create', 'put', 'patch', 'patchPath', 'delete'];

    public function __construct(
        private Spec $spec,
    ) {}

    /**
     * @return array<string, string> relative path => source
     */
    public function files(string $tag, ?string $class = null): array
    {
        $class ??= Naming::className(str_replace(' ', '_', $tag));

        return ['src/Resources/'.$class.'.php' => $this->source($tag, $class)];
    }

    public function source(string $tag, string $class): string
    {
        $operations = array_values(array_filter(
            $this->spec->implementable(),
            static fn (Operation $op): bool => strcasecmp($op->tag(), $tag) === 0,
        ));
        if ($operations === []) {
            throw new SpecException(sprintf('No implementable operations tagged "%s".', $tag));
        }

        $collection = $this->collectionPath($operations);
        $schema = $this->itemSchema($operations);
        if ($schema === null) {
            return $this->endpointSource($tag, $class, $collection, $operations);
        }
        $dto = Naming::className($schema);
        $param = lcfirst($dto);

        $verbs = [];
        $others = [];
        foreach ($operations as $op) {
            $verb = $this->verb($op, $collection);
            if ($verb === null || isset($verbs[$verb])) {
                $others[] = $op;
            } else {
                $verbs[$verb] = $op;
            }
        }
        uksort($verbs, static fn (string $a, string $b): int => array_search($a, self::VERB_ORDER, true) <=> array_search($b, self::VERB_ORDER, true));

        $uses = [
            'Aybarsm\\Apache\\Apisix\\AdminApi\\Attributes\\SpecOperation',
            'Aybarsm\\Apache\\Apisix\\AdminApi\\Dto\\'.$dto,
            'Aybarsm\\Apache\\Apisix\\AdminApi\\Support\\Envelope',
            'Override',
        ];
        if (isset($verbs['list'])) {
            array_push($uses, 'Aybarsm\\Apache\\Apisix\\AdminApi\\Support\\ListQuery', 'Aybarsm\\Apache\\Apisix\\AdminApi\\Support\\Page', 'Generator');
        }
        if (isset($verbs['delete'])) {
            $uses[] = 'Aybarsm\\Apache\\Apisix\\AdminApi\\Support\\DeleteResult';
        }
        if ($others !== []) {
            $uses[] = 'LogicException';
        }
        $uses = array_values(array_unique($uses));
        sort($uses);

        $body = [];
        if (isset($verbs['list'])) {
            $params = array_map(static fn (Parameter $p): string => var_export($p->name, true), $verbs['list']->parametersIn('query'));
            array_push(
                $body,
                sprintf('    /** Query parameters accepted by `%s`. */', $verbs['list']->id),
                sprintf('    public const array LIST_PARAMS = [%s];', implode(', ', $params)),
                '',
            );
        }

        foreach ($verbs as $verb => $op) {
            $body = [...$body, ...$this->method($verb, $op, $dto, $param), ''];
        }
        foreach ($others as $op) {
            $body = [...$body, ...$this->stub($op), ''];
        }

        array_push(
            $body,
            '    #[Override]',
            '    protected function basePath(): array',
            '    {',
            sprintf('        return [%s];', implode(', ', array_map(static fn (string $s): string => var_export($s, true), explode('/', $collection)))),
            '    }',
            '',
            '    #[Override]',
            '    protected function dtoClass(): string',
            '    {',
            sprintf('        return %s::class;', $dto),
            '    }',
        );

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace '.self::NAMESPACE.';',
            '',
            ...array_map(static fn (string $u): string => 'use '.$u.';', $uses),
            '',
            '/**',
            sprintf(' * Spec tag `%s`: %s%s.', $tag, self::PREFIX, $collection),
            ' *',
            sprintf(' * @extends AbstractResource<%s>', $dto),
            ' */',
            sprintf('final readonly class %s extends AbstractResource', $class),
            '{',
            ...$body,
            '}',
            '',
        ]);
    }

    /**
     * Resource without an etcd envelope: every operation becomes a TODO stub on an Endpoint.
     *
     * @param list<Operation> $operations
     */
    private function endpointSource(string $tag, string $class, string $collection, array $operations): string
    {
        $body = [];
        foreach ($operations as $op) {
            $body = [...$body, ...$this->stub($op), ''];
        }
        array_pop($body);

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'namespace '.self::NAMESPACE.';',
            '',
            'use Aybarsm\\Apache\\Apisix\\AdminApi\\Attributes\\SpecOperation;',
            'use LogicException;',
            '',
            '/**',
            sprintf(' * Spec tag `%s`: %s%s.', $tag, self::PREFIX, $collection),
            ' */',
            sprintf('final readonly class %s extends Endpoint', $class),
            '{',
            ...$body,
            '}',
            '',
        ]);
    }

    /**
     * @return list<string>
     */
    private function stub(Operation $op): array
    {
        return [
            sprintf('    #[SpecOperation(%s)]', var_export($op->id, true)),
            sprintf('    public function %s(): mixed', lcfirst(Naming::className($op->id))),
            '    {',
            sprintf("        throw new LogicException('TODO: implement %s %s');", $op->method, $op->path),
            '    }',
        ];
    }

    /**
     * @return list<string>
     */
    private function method(string $verb, Operation $op, string $dto, string $param): array
    {
        $attr = sprintf('    #[SpecOperation(%s)]', var_export($op->id, true));
        $ttl = $this->hasQuery($op, 'ttl');
        $ttlParam = $ttl ? ', ?int $ttl = null' : '';
        $ttlArg = $ttl ? ', $ttl' : '';
        $envelope = sprintf('     * @return Envelope<%s>', $dto);

        return match ($verb) {
            'list' => [
                '    /**',
                sprintf('     * @return Page<%s>', $dto),
                '     */',
                $attr,
                '    public function list(?ListQuery $query = null): Page',
                '    {',
                '        return $this->doList($query, self::LIST_PARAMS);',
                '    }',
                '',
                '    /**',
                '     * Every item across all pages, fetched lazily one page per request.',
                '     *',
                sprintf('     * @return Generator<int, Envelope<%s>, mixed, void>', $dto),
                '     */',
                '    public function lazy(?ListQuery $query = null): Generator',
                '    {',
                '        return $this->doLazy($query, self::LIST_PARAMS);',
                '    }',
            ],
            'get' => ['    /**', $envelope, '     */', $attr, '    public function get(string|int $id): Envelope', '    {', '        return $this->doGet($id);', '    }'],
            'create' => [
                '    /**',
                sprintf('     * @param %s|array<string, mixed> $%s', $dto, $param),
                '     *',
                $envelope,
                '     */',
                $attr,
                sprintf('    public function create(%s|array $%s%s): Envelope', $dto, $param, $ttlParam),
                '    {',
                sprintf('        return $this->doCreate($%s%s);', $param, $ttl ? ', $ttl' : ''),
                '    }',
            ],
            'put' => [
                '    /**',
                sprintf('     * @param %s|array<string, mixed> $%s', $dto, $param),
                '     *',
                $envelope,
                '     */',
                $attr,
                sprintf('    public function put(string|int $id, %s|array $%s%s): Envelope', $dto, $param, $ttlParam),
                '    {',
                sprintf('        return $this->doPut($id, $%s%s);', $param, $ttlArg),
                '    }',
            ],
            'patch' => [
                '    /**',
                '     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key',
                '     *',
                $envelope,
                '     */',
                $attr,
                sprintf('    public function patch(string|int $id, array $changes%s): Envelope', $ttlParam),
                '    {',
                sprintf('        return $this->doPatch($id, $changes%s);', $ttlArg),
                '    }',
            ],
            'patchPath' => [
                '    /**',
                '     * Replaces the value at a slash-separated path, e.g. `plugins/limit-count`.',
                '     *',
                $envelope,
                '     */',
                $attr,
                sprintf('    public function patchPath(string|int $id, string $path, mixed $value%s): Envelope', $ttlParam),
                '    {',
                sprintf('        return $this->doPatchPath($id, $path, $value%s);', $ttlArg),
                '    }',
            ],
            default => [
                $attr,
                sprintf('    public function delete(string|int $id%s): DeleteResult', $this->hasQuery($op, 'force') ? ', bool $force = false' : ''),
                '    {',
                sprintf('        return $this->doDelete($id%s);', $this->hasQuery($op, 'force') ? ', $force' : ''),
                '    }',
            ],
        };
    }

    private function verb(Operation $op, string $collection): ?string
    {
        $collectionPath = self::PREFIX.$collection;
        $isCollection = $op->path === $collectionPath;
        $isItem = preg_match('#^'.preg_quote($collectionPath, '#').'/\{[^/]+\}$#', $op->path) === 1;
        $isSubPath = preg_match('#^'.preg_quote($collectionPath, '#').'/\{[^/]+\}/\{sub_path\}$#', $op->path) === 1;

        return match (true) {
            $isCollection && $op->method === 'GET' => 'list',
            $isCollection && $op->method === 'POST' => 'create',
            $isItem && $op->method === 'GET' => 'get',
            $isItem && $op->method === 'PUT' => 'put',
            $isItem && $op->method === 'PATCH' => 'patch',
            $isItem && $op->method === 'DELETE' => 'delete',
            $isSubPath && $op->method === 'PATCH' => 'patchPath',
            default => null,
        };
    }

    private function hasQuery(Operation $op, string $name): bool
    {
        foreach ($op->parametersIn('query') as $p) {
            if ($p->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Operation> $operations
     */
    private function collectionPath(array $operations): string
    {
        $paths = array_map(static fn (Operation $op): string => $op->path, $operations);
        usort($paths, static fn (string $a, string $b): int => strlen($a) <=> strlen($b));

        return substr($paths[0], strlen(self::PREFIX));
    }

    /**
     * Schema wrapped by the item envelope (e.g. `Upstream` for `UpstreamEnvelope`).
     *
     * @param list<Operation> $operations
     */
    private function itemSchema(array $operations): ?string
    {
        foreach ($operations as $op) {
            foreach ($op->responses as $status => $schema) {
                if (! in_array((string) $status, ['200', '201'], true) || $schema === null) {
                    continue;
                }
                $ref = $schema['$ref'] ?? null;
                if (is_string($ref) && str_ends_with($ref, 'Envelope') && ! str_ends_with($ref, 'ListEnvelope')) {
                    return substr(RefResolver::refName($ref), 0, -strlen('Envelope'));
                }
            }
        }

        return null;
    }
}
