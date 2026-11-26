<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Orm\Repository;

/**
 * @extends Repository<BudgetChange>
 */
class BudgetChangeRepository extends Repository
{
    /**
     * @return list<BudgetChange>
     */
    public function latestFor(string $unitId, int $limit = 20): array
    {
        /** @var list<BudgetChange> $changes */
        $changes = array_values($this->ordered(['unitId' => $unitId], $limit)->toArray());

        return $changes;
    }
}
