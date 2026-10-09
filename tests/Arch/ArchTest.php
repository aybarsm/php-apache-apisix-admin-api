<?php

declare(strict_types=1);

use Aybarsm\Apache\Apisix\AdminApi\Tests\Support\SourceClasses;

arch('source uses strict types')
    ->expect('Aybarsm\Apache\Apisix\AdminApi')
    ->toUseStrictTypes();

arch('dev tooling uses strict types')
    ->expect('Aybarsm\Apache\Apisix\AdminApi\Dev')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();

arch('enums live in the Enums namespace')
    ->expect('Aybarsm\Apache\Apisix\AdminApi\Enums')
    ->toBeEnums();

arch('exceptions extend the package root exception')
    ->expect('Aybarsm\Apache\Apisix\AdminApi\Exceptions')
    ->toExtend(Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApacheApisixApiException::class)
    ->ignoring(Aybarsm\Apache\Apisix\AdminApi\Exceptions\ApacheApisixApiException::class);

it('declares every concrete class final', function (): void {
    $offenders = [];
    foreach (SourceClasses::all() as $class) {
        if (! $class->isInterface() && ! $class->isTrait() && ! $class->isEnum() && ! $class->isAbstract() && ! $class->isFinal()) {
            $offenders[] = $class->getName();
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps DTOs and support value objects readonly', function (): void {
    $offenders = [];
    foreach ([...SourceClasses::inNamespace('Dto'), ...SourceClasses::inNamespace('Support'), ...SourceClasses::inNamespace('Attributes')] as $class) {
        if (! $class->isInterface() && ! $class->isTrait() && ! $class->isReadOnly()) {
            $offenders[] = $class->getName();
        }
    }

    expect($offenders)->toBe([]);
});

it('backs every enum', function (): void {
    $offenders = [];
    foreach (SourceClasses::all() as $class) {
        if ($class->isEnum() && ! (new ReflectionEnum($class->getName()))->isBacked()) {
            $offenders[] = $class->getName();
        }
    }

    expect($offenders)->toBe([]);
});

it('types every class constant', function (): void {
    $offenders = [];
    foreach (SourceClasses::all() as $class) {
        foreach ($class->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() === $class->getName() && ! $constant->isEnumCase() && ! $constant->hasType()) {
                $offenders[] = $class->getName().'::'.$constant->getName();
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('marks every overriding or implementing method with #[Override]', function (): void {
    $offenders = [];
    foreach (SourceClasses::all() as $class) {
        if ($class->isInterface()) {
            continue;
        }
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName() || $method->isConstructor() || $method->isInternal()) {
                continue;
            }
            try {
                $method->getPrototype();
            } catch (ReflectionException) {
                continue;
            }
            if ($method->getAttributes(Override::class) === []) {
                $offenders[] = $class->getName().'::'.$method->getName();
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('never exposes PSR-7 messages or internal transport types through the public API', function (): void {
    $forbidden = [
        Psr\Http\Message\MessageInterface::class,
        Psr\Http\Message\ResponseInterface::class,
        Psr\Http\Message\RequestInterface::class,
        Psr\Http\Message\StreamInterface::class,
        Aybarsm\Apache\Apisix\AdminApi\Internal\Response::class,
        Aybarsm\Apache\Apisix\AdminApi\Internal\Transport::class,
    ];

    $typeNames = static function (?ReflectionType $type): array {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            return array_merge(...array_map(static fn (ReflectionType $t): array => $t instanceof ReflectionNamedType ? [$t->getName()] : [], $type->getTypes()));
        }

        return [];
    };

    $offenders = [];
    foreach (SourceClasses::all() as $class) {
        if (str_contains($class->getName(), '\\Internal\\')) {
            continue;
        }
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $types = $typeNames($method->getReturnType());
            foreach ($method->getParameters() as $parameter) {
                array_push($types, ...$typeNames($parameter->getType()));
            }
            foreach ($types as $type) {
                foreach ($forbidden as $bad) {
                    if ($type === $bad || is_subclass_of($type, $bad)) {
                        $offenders[] = $class->getName().'::'.$method->getName().' uses '.$type;
                    }
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
