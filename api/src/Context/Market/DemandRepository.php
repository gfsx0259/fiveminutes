<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<Demand>
 */
class DemandRepository extends Repository
{
    /**
     * @return Collection<int, Demand>
     */
    public function findOpenByOrganization(string $organizationId): Collection
    {
        return $this->collection([
            'organizationId' => $organizationId,
            'status' => ListingStatus::Open->value,
        ]);
    }
}
