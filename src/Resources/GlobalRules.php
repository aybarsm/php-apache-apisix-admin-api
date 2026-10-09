<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Resources;

use Aybarsm\Apache\Apisix\AdminApi\Attributes\SpecOperation;
use Aybarsm\Apache\Apisix\AdminApi\Dto\GlobalRule;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;
use Override;

/**
 * Spec tag `Global Rules`: /apisix/admin/global_rules.
 *
 * @extends AbstractResource<GlobalRule>
 */
final readonly class GlobalRules extends AbstractResource
{
    /** Query parameters accepted by `listGlobalRules`. */
    public const array LIST_PARAMS = ['name', 'label', 'page', 'page_size'];

    /**
     * @return Page<GlobalRule>
     */
    #[SpecOperation('listGlobalRules')]
    public function list(?ListQuery $query = null): Page
    {
        return $this->doList($query, self::LIST_PARAMS);
    }

    /**
     * Every item across all pages, fetched lazily one page per request.
     *
     * @return Generator<int, Envelope<GlobalRule>, mixed, void>
     */
    public function lazy(?ListQuery $query = null): Generator
    {
        return $this->doLazy($query, self::LIST_PARAMS);
    }

    /**
     * @return Envelope<GlobalRule>
     */
    #[SpecOperation('getGlobalRule')]
    public function get(string|int $id): Envelope
    {
        return $this->doGet($id);
    }

    /**
     * @param GlobalRule|array<string, mixed> $globalRule
     *
     * @return Envelope<GlobalRule>
     */
    #[SpecOperation('createGlobalRuleById')]
    public function put(string|int $id, GlobalRule|array $globalRule, ?int $ttl = null): Envelope
    {
        return $this->doPut($id, $globalRule, $ttl);
    }

    /**
     * @param array<string, mixed> $changes JSON merge-patch; `null` removes a key
     *
     * @return Envelope<GlobalRule>
     */
    #[SpecOperation('updateGlobalRule')]
    public function patch(string|int $id, array $changes, ?int $ttl = null): Envelope
    {
        return $this->doPatch($id, $changes, $ttl);
    }

    /**
     * Replaces the value at a slash-separated path, e.g. `plugins/limit-count`.
     *
     * @return Envelope<GlobalRule>
     */
    #[SpecOperation('patchGlobalRulesSubPath')]
    public function patchPath(string|int $id, string $path, mixed $value, ?int $ttl = null): Envelope
    {
        return $this->doPatchPath($id, $path, $value, $ttl);
    }

    #[SpecOperation('deleteGlobalRule')]
    public function delete(string|int $id, bool $force = false): DeleteResult
    {
        return $this->doDelete($id, $force);
    }

    #[Override]
    protected function basePath(): array
    {
        return ['global_rules'];
    }

    #[Override]
    protected function dtoClass(): string
    {
        return GlobalRule::class;
    }
}
