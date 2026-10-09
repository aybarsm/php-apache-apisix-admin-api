<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Internal;

use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Generator;

/**
 * Lazy iteration over paginated list endpoints.
 *
 * @internal
 */
final class Pagination
{
    /**
     * Yields every item, fetching one page per request. Stops on the first of:
     * an empty page, a short page, or reaching `total`. Endpoints whose spec
     * declares no pagination are fetched once.
     *
     * @template T of Dto
     *
     * @param callable(ListQuery): Page<T> $fetch
     * @param list<string>                 $supported query parameters the endpoint declares
     *
     * @return Generator<int, Envelope<T>, mixed, void>
     */
    public static function lazy(callable $fetch, ?ListQuery $query, array $supported, int $defaultPageSize): Generator
    {
        $query ??= new ListQuery();

        if (! in_array(ListQuery::PAGE, $supported, true) || ! in_array(ListQuery::PAGE_SIZE, $supported, true)) {
            yield from $fetch($query)->items;

            return;
        }

        $pageSize = $query->pageSize ?? $defaultPageSize;
        $page = $query->page ?? 1;

        while (true) {
            $result = $fetch($query->withPage($page, $pageSize));
            foreach ($result->items as $item) {
                yield $item;
            }

            $count = count($result->items);
            if ($count === 0 || $count < $pageSize || $page * $pageSize >= $result->total) {
                return;
            }
            $page++;
        }
    }
}
