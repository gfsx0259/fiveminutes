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
        return $this->ordered([
            'organizationId' => $organizationId,
            'status' => ListingStatus::Open->value,
        ]);
    }

    /**
     * @return Collection<int, Demand>
     */
    public function findByPartner(string $organizationId, string $partnerId): Collection
    {
        return $this->ordered([
            'organizationId' => $organizationId,
            'partnerId' => $partnerId,
        ]);
    }

    public function findById(string $id): ?Demand
    {
        $found = $this->findByPK($id);

        return $found instanceof Demand ? $found : null;
    }
}
