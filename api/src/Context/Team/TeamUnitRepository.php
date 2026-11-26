<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Orm\Repository;

/**
 * @extends Repository<TeamUnit>
 */
class TeamUnitRepository extends Repository
{
    /**
     * @return list<string>
     */
    public function budgetedAccountIds(): array
    {
        $ids = [];
        foreach ($this->select()->where('budgetCents', '!=', null)->fetchAll() as $unit) {
            if ($unit instanceof TeamUnit) {
                $ids[$unit->getAccountId()] = true;
            }
        }

        return array_keys($ids);
    }

    public function treeOf(string $accountId): TeamTree
    {
        /** @var list<TeamUnit> $units */
        $units = array_values($this->select()->where(['accountId' => $accountId])->orderBy('createdAt')->fetchAll());

        return new TeamTree($units);
    }
}
