<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<Slot>
 */
class SlotRepository extends Repository
{
    /**
     * @return Collection<int, Slot>
     */
    public function findOpenByOrganization(string $organizationId): Collection
    {
        return $this->ordered([
            'organizationId' => $organizationId,
            'status' => ListingStatus::Open->value,
        ]);
    }

    /**
     * @return Collection<int, Slot>
     */
    public function findByBuyer(string $organizationId, string $buyerId): Collection
    {
        return $this->ordered([
            'organizationId' => $organizationId,
            'buyerId' => $buyerId,
        ]);
    }

    public function findById(string $id): ?Slot
    {
        $found = $this->findByPK($id);

        return $found instanceof Slot ? $found : null;
    }
}
