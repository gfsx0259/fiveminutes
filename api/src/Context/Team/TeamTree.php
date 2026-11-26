<?php

declare(strict_types=1);

namespace App\Context\Team;

final readonly class TeamTree
{
    /** @var array<string, TeamUnit> */
    private array $units;

    /**
     * @param list<TeamUnit> $units
     */
    public function __construct(array $units)
    {
        $indexed = [];
        foreach ($units as $unit) {
            $indexed[$unit->getId()] = $unit;
        }
        $this->units = $indexed;
    }

    public function find(?string $id): ?TeamUnit
    {
        return $id === null ? null : $this->units[$id] ?? null;
    }

    public function reaches(?string $root, ?string $unitId): bool
    {
        return $unitId === null || ($this->find($unitId) !== null && $this->covers($root, $unitId));
    }

    public function covers(?string $root, ?string $unitId): bool
    {
        return $root === null || $this->below($root, $unitId) || $root === $unitId;
    }

    public function below(?string $root, ?string $unitId): bool
    {
        if ($unitId === null) {
            return false;
        }
        if ($root === null) {
            return true;
        }

        foreach ($this->path($unitId) as $ancestor) {
            if ($ancestor->getId() === $root && $ancestor->getId() !== $unitId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<TeamUnit>
     */
    public function within(?string $root): array
    {
        return array_values(array_filter(
            $this->units,
            fn (TeamUnit $unit): bool => $this->covers($root, $unit->getId()),
        ));
    }

    /**
     * @return list<TeamUnit>
     */
    public function children(?string $parentId): array
    {
        return array_values(array_filter(
            $this->units,
            static fn (TeamUnit $unit): bool => $unit->getParentId() === $parentId,
        ));
    }

    /**
     * @return list<TeamUnit>
     */
    public function path(?string $unitId): array
    {
        $path = [];
        $unit = $this->find($unitId);
        while ($unit !== null && count($path) <= count($this->units)) {
            array_unshift($path, $unit);
            $unit = $this->find($unit->getParentId());
        }

        return $path;
    }
}
