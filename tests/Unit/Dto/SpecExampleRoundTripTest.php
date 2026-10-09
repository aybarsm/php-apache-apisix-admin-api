<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\RefResolver;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Scaffold\Naming;
use Aybarsm\Apache\Apisix\AdminApi\Support\Envelope;
use Aybarsm\Apache\Apisix\AdminApi\Support\Page;
use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SpecExamples;

/**
 * Every response example of an implemented operation whose (override-corrected)
 * response is `XEnvelope` / `XListEnvelope` must hydrate into DTO `X` and
 * serialise back unchanged. New resources join automatically once their DTO exists.
 * Overridden responses are skipped: their spec examples describe the shape the override corrects.
 */
dataset('spec response examples', function (): Generator {
    foreach (SpecExamples::spec()->implementable() as $operation) {
        foreach ($operation->responses as $status => $schema) {
            if (isset($operation->override?->responses[(string) $status])) {
                continue; // examples document the original (wrong) shape the override corrects
            }
            $ref = is_array($schema) && is_string($schema['$ref'] ?? null) ? RefResolver::refName($schema['$ref']) : null;
            if ($ref === null || ! str_ends_with($ref, 'Envelope')) {
                continue;
            }
            $kind = str_ends_with($ref, 'ListEnvelope') ? 'page' : 'envelope';
            $class = 'Aybarsm\\Apache\\Apisix\\AdminApi\\Dto\\'.Naming::className(substr($ref, 0, -strlen($kind === 'page' ? 'ListEnvelope' : 'Envelope')));
            if (! class_exists($class)) {
                continue;
            }
            foreach (SpecExamples::response($operation->id, (string) $status) as $name => $example) {
                yield "{$operation->id} {$status} #{$name}" => [$example, $class, $kind];
            }
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

it('round-trips schema-level examples', function (string $class): void {
    foreach (SpecExamples::schema($class::SCHEMA) as $example) {
        expect(canonical($class::fromArray($example)->toArray()))->toBe(canonical($example));
    }
})->with(fn (): array => array_values(array_filter(dtoClasses(), fn (string $c): bool => SpecExamples::schema($c::SCHEMA) !== [])));
