<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Orm\Repository;
use DateTimeInterface;

/**
 * @extends Repository<BudgetAlert>
 */
class BudgetAlertRepository extends Repository
{
    public function sent(string $unitId, DateTimeInterface $periodStart, int $threshold): bool
    {
        return $this->select()
            ->where(['unitId' => $unitId, 'periodStart' => $periodStart, 'threshold' => $threshold])
            ->count() > 0;
    }
}
