<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\ClientConfig;
use Aybarsm\Apache\Apisix\AdminApi\Dto\Timeout;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\HydrationException;
use Aybarsm\Apache\Apisix\AdminApi\Exceptions\InvalidArgumentException;
use Aybarsm\Apache\Apisix\AdminApi\Support\DeleteResult;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\ListQuery;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Support\ResourceId;

describe('ResourceId', function (): void {
    it('accepts spec-valid ids', function (string|int $id, string $expected): void {
        expect(ResourceId::assert($id))->toBe($expected);
    })->with([['abc', 'abc'], ['a-b_c.d', 'a-b_c.d'], [42, '42'], [str_repeat('a', 64), str_repeat('a', 64)]]);

    it('rejects invalid ids', function (string|int $id): void {
        expect(fn () => ResourceId::assert($id))->toThrow(InvalidArgumentException::class);
    })->with(['', 'a/b', 'a b', str_repeat('a', 65), 0, -1]);

    it('validates usernames and names', function (): void {
        expect(ResourceId::assertUsername('jack_1-x'))->toBe('jack_1-x')
            ->and(fn () => ResourceId::assertUsername('jack.x'))->toThrow(InvalidArgumentException::class)
            ->and(ResourceId::assertName('limit-count', 'plugin'))->toBe('limit-count')
            ->and(fn () => ResourceId::assertName('a/b', 'plugin'))->toThrow(InvalidArgumentException::class);
    });
});

describe('ListQuery', function (): void {
    it('builds supported query parameters', function (): void {
        $query = new ListQuery(page: 2, pageSize: 50, name: 'n', label: 'env:dev');

        expect($query->toQuery(['page', 'page_size', 'name', 'label']))->toBe(['page' => 2, 'page_size' => 50, 'name' => 'n', 'label' => 'env:dev'])
            ->and($query->isPaginated())->toBeTrue()
            ->and((new ListQuery())->isPaginated())->toBeFalse();
    });

    it('rejects filters the endpoint does not support', function (): void {
        expect(fn () => (new ListQuery(uri: '/x'))->toQuery(['page', 'page_size']))
            ->toThrow(InvalidArgumentException::class, '"uri"');
    });

    it('validates pagination bounds', function (?int $page, ?int $size): void {
        expect(fn () => new ListQuery($page, $size))->toThrow(InvalidArgumentException::class);
    })->with([[0, null], [null, 9], [null, 501]]);

    it('derives the next page', function (): void {
        $next = (new ListQuery(page: 1, pageSize: 20, name: 'x'))->withPage(2);

        expect($next->page)->toBe(2)->and($next->pageSize)->toBe(20)->and($next->name)->toBe('x');
    });
});

describe('Envelope and Page', function (): void {
    $envelope = ['key' => '/apisix/upstreams/u1', 'value' => ['connect' => 1, 'send' => 1, 'read' => 1], 'createdIndex' => 3, 'modifiedIndex' => 4];

    it('hydrates envelopes', function () use ($envelope): void {
        $e = Envelope::fromArray($envelope, Timeout::class);

        expect($e->value)->toBeInstanceOf(Timeout::class)
            ->and($e->id())->toBe('u1')
            ->and($e->createdIndex)->toBe(3)
            ->and($e->toArray())->toBe($envelope);
    });

    it('hydrates pages', function () use ($envelope): void {
        $page = Page::fromArray(['total' => 30, 'list' => [$envelope, $envelope]], Timeout::class, 1, 10);

        expect($page)->toHaveCount(2)
            ->and($page->total)->toBe(30)
            ->and($page->values())->each->toBeInstanceOf(Timeout::class)
            ->and(iterator_to_array($page))->toHaveCount(2);
    });

    it('computes hasMore', function (int $total, int $items, ?int $page, ?int $size, bool $expected) use ($envelope): void {
        $p = new Page($total, array_fill(0, $items, Envelope::fromArray($envelope, Timeout::class)), $page, $size);

        expect($p->hasMore())->toBe($expected);
    })->with([
        'more pages' => [25, 10, 1, 10, true],
        'last full page' => [20, 10, 2, 10, false],
        'short page' => [25, 5, 3, 10, false],
        'unpaginated' => [25, 25, null, null, false],
    ]);

    it('defaults total to the item count and reports paths', function () use ($envelope): void {
        expect(Page::fromArray(['list' => [$envelope]], Timeout::class)->total)->toBe(1)
            ->and(fn () => Page::fromArray(['list' => [['key' => 'k']]], Timeout::class))
            ->toThrow(HydrationException::class, 'Page.list[0].value: required value is missing');
    });
});

it('hydrates delete results', function (): void {
    expect(DeleteResult::fromArray(['key' => '/apisix/routes/1', 'deleted' => '1']))
        ->toEqual(new DeleteResult('/apisix/routes/1', '1'))
        ->and(DeleteResult::fromArray(['deleted' => 1])->deleted)->toBe('1');
});

describe('ClientConfig', function (): void {
    it('normalises the base URI', function (): void {
        expect((new ClientConfig('http://host:9180/ '))->baseUri)->toBe('http://host:9180');
    });

    it('rejects invalid settings', function (string $uri, float $timeout): void {
        expect(fn () => new ClientConfig($uri, 'k', $timeout))->toThrow(InvalidArgumentException::class);
    })->with([['host:9180', 1.0], ['ftp://host', 1.0], ['http://host', 0.0]]);

    it('masks the api key in debug output', function (): void {
        expect(print_r(new ClientConfig('http://h', 'secret-key'), true))->not->toContain('secret-key');
    });
});
