<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Support;

use ArrayIterator;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Contracts\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Internal\Data;
use Countable;
use IteratorAggregate;
use Override;

/**
 * One page of a list endpoint: `{total, list: Envelope[]}`.
 *
 * @template T of Dto
 *
 * @implements IteratorAggregate<int, Envelope<T>>
 */
final readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param list<Envelope<T>> $items
     * @param int|null          $page     requested page (null when unpaginated)
     * @param int|null          $pageSize requested page size (null when unpaginated)
     */
    public function __construct(
        public int $total,
        public array $items,
        public ?int $page = null,
        public ?int $pageSize = null,
    ) {}

    /**
     * @template D of Dto
     *
     * @param array<array-key, mixed> $data
     * @param class-string<D>         $class
     *
     * @return self<D>
     */
    public static function fromArray(array $data, string $class, ?int $page = null, ?int $pageSize = null, string $path = 'Page'): self
    {
        $x = Data::of($data, $path);
        $items = [];
        foreach ($x->list('list') ?? [] as $i => $item) {
            $items[] = Envelope::fromArray(Data::assertMap($item, sprintf('%s.list[%d]', $path, $i)), $class, sprintf('%s.list[%d]', $path, $i));
        }

        return new self($x->int('total') ?? count($items), $items, $page, $pageSize);
    }

    /**
     * Whether pages beyond this one exist (always false when unpaginated).
     */
    public function hasMore(): bool
    {
        if ($this->page === null || $this->pageSize === null) {
            return false;
        }

        return $this->page * $this->pageSize < $this->total && count($this->items) >= $this->pageSize;
    }

    /**
     * @return list<T>
     */
    public function values(): array
    {
        return array_map(static fn (Envelope $e): Dto => $e->value, $this->items);
    }

    #[Override]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ArrayIterator<int, Envelope<T>>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
