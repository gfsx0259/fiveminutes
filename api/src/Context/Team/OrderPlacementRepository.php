<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Orm\Repository;

/**
 * @extends Repository<OrderPlacement>
 */
class OrderPlacementRepository extends Repository
{
    /**
     * @return array<string, OrderPlacement>
     */
    public function byOrderOf(string $accountId): array
    {
        $placements = [];
        foreach ($this->select()->where(['accountId' => $accountId])->fetchAll() as $placement) {
            if ($placement instanceof OrderPlacement) {
                $placements[$placement->getOrderId()] = $placement;
            }
        }

        return $placements;
    }

    /**
     * @param list<string> $unitIds
     * @return list<string>
     */
    public function orderIdsIn(string $accountId, array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        $ids = [];
        foreach ($this->select()->where(['accountId' => $accountId])->where('unitId', 'in', $unitIds)->fetchAll() as $placement) {
            if ($placement instanceof OrderPlacement) {
                $ids[] = $placement->getOrderId();
            }
        }

        return $ids;
    }

    /**
     * @return list<OrderPlacement>
     */
    public function findInUnit(string $unitId): array
    {
        /** @var list<OrderPlacement> $placements */
        $placements = array_values($this->select()->where(['unitId' => $unitId])->fetchAll());

        return $placements;
    }
}
