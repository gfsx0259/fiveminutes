<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<Acceptance>
 */
class AcceptanceRepository extends Repository
{
    /**
     * @return Collection<int, Acceptance>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        return $this->collection(['organizationId' => $organizationId]);
    }

    public function findBySlot(string $slotId): ?Acceptance
    {
        $found = $this->findOne(['slotId' => $slotId]);

        return $found instanceof Acceptance ? $found : null;
    }

    /**
     * @return Collection<int, Acceptance>
     */
    public function findByBuyer(string $buyerId): Collection
    {
        return $this->ordered(['buyerId' => $buyerId]);
    }

    /**
     * @return Collection<int, Acceptance>
     */
    public function findByPartner(string $partnerId): Collection
    {
        return $this->ordered(['partnerId' => $partnerId]);
    }

    public function findByDemand(string $demandId): ?Acceptance
    {
        $found = $this->findOne(['demandId' => $demandId]);

        return $found instanceof Acceptance ? $found : null;
    }
}
