<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Overrides;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\SpecException;

function overridesFixture(array $patch = []): array
{
    return array_replace([
        'spec' => 'v1.0.0.json',
        'operations' => [
            'getId]' => ['rename' => 'getThing', 'issue' => 'i', 'actual' => 'a', 'evidence' => 'e'],
        ],
        'exclude' => ['dropMe' => 'reason'],
        'notes' => [['topic' => 't', 'detail' => 'd']],
    ], $patch);
}

it('parses a valid overrides document', function (): void {
    $overrides = Overrides::fromArray(overridesFixture());

    expect($overrides->operations['getId]']->rename)->toBe('getThing')
        ->and($overrides->exclude)->toBe(['dropMe' => 'reason'])
        ->and($overrides->notes)->toHaveCount(1);
});

it('validates the shipped overrides files', function (string $file): void {
    expect(Overrides::fromFile($file))->toBeInstanceOf(Overrides::class);
})->with(fn (): array => glob(projectRoot('resources/apache-apisix/v*.overrides.json')) ?: []);

it('rejects invalid documents', function (array $patch, string $message): void {
    expect(fn () => Overrides::fromArray(overridesFixture($patch)))
        ->toThrow(SpecException::class, $message);
})->with([
    'unknown top-level key' => [['extra' => 1], 'unknown key "extra"'],
    'bad spec name' => [['spec' => 'spec.json'], 'must look like'],
    'missing evidence' => [['operations' => ['x' => ['issue' => 'i', 'actual' => 'a']]], 'evidence'],
    'unknown operation key' => [['operations' => ['x' => ['issue' => 'i', 'actual' => 'a', 'evidence' => 'e', 'foo' => 1]]], 'unknown key "foo"'],
    'bad rename' => [['operations' => ['x' => ['rename' => 'Bad-Name', 'issue' => 'i', 'actual' => 'a', 'evidence' => 'e']]], 'lowerCamelCase'],
    'bad response status' => [['operations' => ['x' => ['responses' => ['2xx' => []], 'issue' => 'i', 'actual' => 'a', 'evidence' => 'e']]], 'invalid status'],
    'empty exclusion reason' => [['exclude' => ['x' => ' ']], 'must not be empty'],
    'overridden and excluded' => [['exclude' => ['getId]' => 'r']], 'both overridden and excluded'],
]);
