<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Support;

use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;

/**
 * Filters and pagination for list endpoints.
 *
 * Without `page`/`pageSize` APISIX returns every item. Each resource accepts
 * only the filters its spec declares; others raise InvalidArgumentException.
 */
final readonly class ListQuery
{
    public const int MIN_PAGE_SIZE = 10;

    public const int MAX_PAGE_SIZE = 500;

    public const string PAGE = 'page';

    public const string PAGE_SIZE = 'page_size';

    public const string NAME = 'name';

    public const string LABEL = 'label';

    public const string URI = 'uri';

    public const string FILTER = 'filter';

    /**
     * @param string|null $name   regex matched against `name`
     * @param string|null $label  label key; matches resources carrying that key (any value)
     * @param string|null $uri    regex matched against `uri`/`uris` (routes)
     * @param string|null $filter URL-encoded expression, e.g. `service_id=1`
     */
    public function __construct(
        public ?int $page = null,
        public ?int $pageSize = null,
        public ?string $name = null,
        public ?string $label = null,
        public ?string $uri = null,
        public ?string $filter = null,
    ) {
        if ($page !== null && $page < 1) {
            throw new InvalidArgumentException(sprintf('page must be >= 1, got %d.', $page));
        }
        if ($pageSize !== null && ($pageSize < self::MIN_PAGE_SIZE || $pageSize > self::MAX_PAGE_SIZE)) {
            throw new InvalidArgumentException(sprintf('pageSize must be between %d and %d, got %d.', self::MIN_PAGE_SIZE, self::MAX_PAGE_SIZE, $pageSize));
        }
    }

    public function withPage(int $page, ?int $pageSize = null): self
    {
        return new self($page, $pageSize ?? $this->pageSize, $this->name, $this->label, $this->uri, $this->filter);
    }

    public function isPaginated(): bool
    {
        return $this->page !== null || $this->pageSize !== null;
    }

    /**
     * Query parameters, restricted to those the endpoint supports.
     *
     * @param list<string> $supported query parameter names declared by the spec
     *
     * @return array<string, string|int>
     */
    public function toQuery(array $supported): array
    {
        $all = [
            self::PAGE => $this->page,
            self::PAGE_SIZE => $this->pageSize,
            self::NAME => $this->name,
            self::LABEL => $this->label,
            self::URI => $this->uri,
            self::FILTER => $this->filter,
        ];

        $query = [];
        foreach ($all as $param => $value) {
            if ($value === null) {
                continue;
            }
            if (! in_array($param, $supported, true)) {
                throw new InvalidArgumentException(sprintf('This endpoint does not support the "%s" query parameter.', $param));
            }
            $query[$param] = $value;
        }

        return $query;
    }
}
