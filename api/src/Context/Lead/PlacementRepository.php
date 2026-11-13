<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Orm\Repository;

/**
 * @extends Repository<Placement>
 */
class PlacementRepository extends Repository
{
    public function findByLead(string $leadUuid): ?Placement
    {
        $found = $this->findByPK($leadUuid);

        return $found instanceof Placement ? $found : null;
    }
}
