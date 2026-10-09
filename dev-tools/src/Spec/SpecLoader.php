<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Operation;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Parameter;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Spec;

/**
 * Loads `resources/apache-apisix/v{version}.json` and merges its overrides file.
 */
final readonly class SpecLoader
{
    public const string RESOURCES_DIR = 'resources/apache-apisix';

    private const array HTTP_METHODS = ['get', 'put', 'post', 'patch', 'delete', 'head', 'options'];

    public function __construct(
        private string $directory,
    ) {}

    public static function forProject(string $root): self
    {
        return new self(rtrim($root, '/').'/'.self::RESOURCES_DIR);
    }

    /**
     * Available spec versions, ascending.
     *
     * @return list<string>
     */
    public function versions(): array
    {
        $versions = [];
        foreach (glob($this->directory.'/v*.json') ?: [] as $file) {
            if (preg_match('/^v(\d+\.\d+\.\d+)\.json$/', basename($file), $m) === 1) {
                $versions[] = $m[1];
            }
        }
        usort($versions, static fn (string $a, string $b): int => version_compare($a, $b));

        return $versions;
    }

    public function latest(): string
    {
        $versions = $this->versions();
        if ($versions === []) {
            throw new SpecException(sprintf('No spec files found in %s', $this->directory));
        }

        return $versions[array_key_last($versions)];
    }

    public function specPath(string $version): string
    {
        return sprintf('%s/v%s.json', $this->directory, ltrim($version, 'v'));
    }

    public function overridesPath(string $version): string
    {
        return sprintf('%s/v%s.overrides.json', $this->directory, ltrim($version, 'v'));
    }

    public function load(?string $version = null): Spec
    {
        $version = ltrim($version ?? $this->latest(), 'v');
        $document = Json::decodeFile($this->specPath($version));
        $overridesPath = $this->overridesPath($version);
        $overrides = is_file($overridesPath)
            ? Overrides::fromFile($overridesPath)
            : new Overrides(sprintf('v%s.json', $version));

        if ($overrides->spec !== sprintf('v%s.json', $version)) {
            throw new SpecException(sprintf('%s targets "%s", expected "v%s.json"', basename($overridesPath), $overrides->spec, $version));
        }

        $refs = new RefResolver($document);

        return new Spec($version, $document, $overrides, $refs, $this->operations($document, $refs, $overrides));
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, Operation>
     */
    private function operations(array $document, RefResolver $refs, Overrides $overrides): array
    {
        $operations = [];
        $seenSpecIds = [];

        foreach (Json::map($document['paths'] ?? null, 'paths') as $path => $item) {
            $item = Json::map($item, 'paths.'.$path);
            $pathParams = $this->parameters($item['parameters'] ?? [], $refs, $path);

            foreach (self::HTTP_METHODS as $method) {
                if (! isset($item[$method])) {
                    continue;
                }

                $ctx = sprintf('%s %s', strtoupper($method), $path);
                $raw = Json::map($item[$method], $ctx);
                $specId = Json::nonEmptyString($raw['operationId'] ?? null, $ctx.'.operationId');
                if (isset($seenSpecIds[$specId])) {
                    throw new SpecException(sprintf('Duplicate operationId "%s" (%s)', $specId, $ctx));
                }
                $seenSpecIds[$specId] = true;

                $override = $overrides->operations[$specId] ?? null;
                $id = $override->rename ?? $specId;

                $params = $pathParams;
                foreach ($this->parameters($raw['parameters'] ?? [], $refs, $ctx) as $key => $param) {
                    $params[$key] = $param;
                }

                $responses = $this->responses($raw['responses'] ?? [], $refs, $ctx);
                foreach ($override->responses ?? [] as $status => $schema) {
                    $responses[$status] = $schema;
                }

                if (isset($operations[$id])) {
                    throw new SpecException(sprintf('Canonical operationId "%s" is used twice', $id));
                }

                $operations[$id] = new Operation(
                    id: $id,
                    specId: $specId,
                    method: strtoupper($method),
                    path: $path,
                    tags: array_values(array_map(strval(...), array_filter(Json::list($raw['tags'] ?? [], $ctx.'.tags'), is_string(...)))),
                    summary: is_string($raw['summary'] ?? null) ? $raw['summary'] : '',
                    parameters: array_values($params),
                    requestSchema: $this->requestSchema($raw['requestBody'] ?? null, $refs, $ctx),
                    responses: $responses,
                    excluded: $overrides->exclude[$specId] ?? null,
                    override: $override,
                );
            }
        }

        foreach (array_keys($overrides->operations + $overrides->exclude) as $specId) {
            if (! isset($seenSpecIds[$specId])) {
                throw new SpecException(sprintf('Overrides reference unknown operationId "%s"', $specId));
            }
        }

        ksort($operations);

        return $operations;
    }

    /**
     * @return array<string, Parameter> keyed by "in:name"
     */
    private function parameters(mixed $raw, RefResolver $refs, string $ctx): array
    {
        $params = [];
        foreach (Json::list($raw, $ctx.'.parameters') as $i => $param) {
            $param = $refs->resolve(Json::map($param, sprintf('%s.parameters[%d]', $ctx, $i)));
            $name = Json::string($param['name'] ?? null, $ctx.'.parameters.name');
            $in = Json::string($param['in'] ?? null, $ctx.'.parameters.in');
            $params[$in.':'.$name] = new Parameter(
                name: $name,
                in: $in,
                required: ($param['required'] ?? false) === true || $in === 'path',
                schema: Json::optionalMap($param['schema'] ?? null, $ctx.'.parameters.'.$name.'.schema'),
            );
        }

        return $params;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestSchema(mixed $raw, RefResolver $refs, string $ctx): ?array
    {
        if ($raw === null) {
            return null;
        }

        $body = $refs->resolve(Json::map($raw, $ctx.'.requestBody'));

        return $this->jsonSchema($body, $ctx.'.requestBody');
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function responses(mixed $raw, RefResolver $refs, string $ctx): array
    {
        $out = [];
        foreach (Json::map($raw, $ctx.'.responses') as $status => $response) {
            $response = $refs->resolve(Json::map($response, sprintf('%s.responses.%s', $ctx, $status)));
            $out[$status] = $this->jsonSchema($response, sprintf('%s.responses.%s', $ctx, $status));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $holder a requestBody or response object
     *
     * @return array<string, mixed>|null
     */
    private function jsonSchema(array $holder, string $ctx): ?array
    {
        $content = Json::optionalMap($holder['content'] ?? null, $ctx.'.content');
        foreach ($content as $mediaType => $media) {
            if (str_contains($mediaType, 'json')) {
                return Json::optionalMap(Json::map($media, $ctx.'.'.$mediaType)['schema'] ?? null, $ctx.'.schema');
            }
        }

        return null;
    }
}
