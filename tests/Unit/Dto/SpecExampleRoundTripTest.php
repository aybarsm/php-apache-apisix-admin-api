<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dto;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

/**
 * Every spec response example must hydrate into its DTO and serialise back unchanged.
 *
 * operationId => [status, DTO class, 'envelope'|'page']
 */
dataset('spec response examples', function (): Generator {
    $operations = [
        'listUpstreams' => ['200', Dto\Upstream::class, 'page'],
        'getUpstream' => ['200', Dto\Upstream::class, 'envelope'],
        'createUpstream' => ['201', Dto\Upstream::class, 'envelope'],
        'createUpstreamById' => ['201', Dto\Upstream::class, 'envelope'],
        'updateUpstream' => ['200', Dto\Upstream::class, 'envelope'],
    ];

    foreach ($operations as $operationId => [$status, $class, $kind]) {
        $examples = SpecExamples::response($operationId, $status);
        foreach ($examples as $name => $example) {
            yield "{$operationId} {$status} #{$name}" => [$example, $class, $kind];
        }
    }
});

it('round-trips spec response examples', function (array $example, string $class, string $kind): void {
    if ($kind === 'page') {
        $page = Page::fromArray($example, $class);
        expect($page->items)->toHaveCount(count($example['list']));
        foreach ($page->items as $i => $envelope) {
            expect(canonical($envelope->toArray()))->toBe(canonical($example['list'][$i]));
        }

        return;
    }

    expect(canonical(Envelope::fromArray($example, $class)->toArray()))->toBe(canonical($example));
})->with('spec response examples');

it('round-trips schema-level examples', function (string $schema, string $class): void {
    $examples = SpecExamples::schema($schema);
    foreach ($examples as $example) {
        expect(canonical($class::fromArray($example)->toArray()))->toBe(canonical($example));
    }
    expect(true)->toBeTrue();
})->with(fn (): array => array_map(
    fn (string $class): array => [$class::SCHEMA, $class],
    array_values(array_filter(dtoClasses(), fn (string $c): bool => SpecExamples::schema($c::SCHEMA) !== [])),
));
