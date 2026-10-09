<?php

declare(strict_types=1);

namespace Aybarsm\Apache\Apisix\AdminApi\Dev\Spec;

use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Mapping;
use Aybarsm\Apache\Apisix\AdminApi\Dev\Spec\Model\Spec;

/**
 * Compares spec operations against `#[SpecOperation]` mappings.
 */
final readonly class CoverageReport
{
    /**
     * @param array<string, list<Mapping>> $mapped          operationId => mappings (implementable ops)
     * @param list<string>                 $unmapped        implementable operationIds without a mapping
     * @param array<string, list<Mapping>> $excludedMapped  excluded operationIds that are still mapped
     * @param list<Mapping>                $unknown         mappings to operationIds absent from the spec
     * @param array<string, list<Mapping>> $duplicates      operationIds mapped more than once
     */
    public function __construct(
        public int $total,
        public int $excluded,
        public array $mapped,
        public array $unmapped,
        public array $excludedMapped,
        public array $unknown,
        public array $duplicates,
    ) {}

    /**
     * @param list<Mapping> $mappings
     */
    public static function build(Spec $spec, array $mappings): self
    {
        $byId = [];
        foreach ($mappings as $mapping) {
            $byId[$mapping->operationId][] = $mapping;
        }

        $mapped = [];
        $unmapped = [];
        $excludedMapped = [];
        $excluded = 0;
        foreach ($spec->operations as $id => $operation) {
            if ($operation->isExcluded()) {
                $excluded++;
                if (isset($byId[$id])) {
                    $excludedMapped[$id] = $byId[$id];
                }
                continue;
            }
            if (isset($byId[$id])) {
                $mapped[$id] = $byId[$id];
            } else {
                $unmapped[] = $id;
            }
        }

        $unknown = [];
        foreach ($byId as $id => $list) {
            if ($spec->operation($id) === null) {
                array_push($unknown, ...$list);
            }
        }

        $duplicates = array_filter($byId, static fn (array $list): bool => count($list) > 1);

        return new self(count($spec->operations), $excluded, $mapped, $unmapped, $excludedMapped, $unknown, $duplicates);
    }

    /**
     * True when no mapping is wrong (unknown, duplicate, or mapped-but-excluded).
     */
    public function isConsistent(): bool
    {
        return $this->unknown === [] && $this->duplicates === [] && $this->excludedMapped === [];
    }

    public function isComplete(): bool
    {
        return $this->isConsistent() && $this->unmapped === [];
    }
}
