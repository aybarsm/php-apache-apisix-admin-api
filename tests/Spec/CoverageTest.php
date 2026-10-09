<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\AttributeScanner;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\CoverageReport;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecLoader;

it('maps #[SpecOperation] attributes consistently with the latest spec', function (): void {
    $report = CoverageReport::build(
        SpecLoader::forProject(projectRoot())->load(),
        AttributeScanner::forProject(projectRoot())->scan(),
    );

    expect($report->unknown)->toBe([], 'Attributes reference operationIds missing from the spec')
        ->and($report->duplicates)->toBe([], 'Operations mapped more than once')
        ->and($report->excludedMapped)->toBe([], 'Excluded operations must not be mapped');
});

it('maps every implementable operation of the latest spec', function (): void {
    $report = CoverageReport::build(
        SpecLoader::forProject(projectRoot())->load(),
        AttributeScanner::forProject(projectRoot())->scan(),
    );

    expect($report->unmapped)->toBe([], 'Run `bin/spec coverage` for details')
        ->and($report->isComplete())->toBeTrue();
});

it('makes bin/spec coverage exit successfully', function (): void {
    [$code, $out] = runSpec('coverage');

    expect($code)->toBe(0)->and($out)->toContain('Coverage complete.');
});
