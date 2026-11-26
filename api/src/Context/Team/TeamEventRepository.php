<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Orm\Repository;

/**
 * @extends Repository<TeamEvent>
 */
class TeamEventRepository extends Repository
{
    /**
     * @param list<string>|null $unitIds
     * @return list<TeamEvent>
     */
    public function latest(string $accountId, ?array $unitIds, int $limit): array
    {
        if ($unitIds === []) {
            return [];
        }

        $select = $this->select()->where(['accountId' => $accountId]);
        if ($unitIds !== null) {
            $select = $select->where('unitId', 'in', $unitIds);
        }

        /** @var list<TeamEvent> $events */
        $events = array_values($select->orderBy('createdAt', 'DESC')->limit($limit)->fetchAll());

        return $events;
    }
}
