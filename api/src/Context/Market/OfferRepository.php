<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<Offer>
 */
class OfferRepository extends Repository
{
    /**
     * @return Collection<int, Offer>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        return $this->ordered(['organizationId' => $organizationId]);
    }

    /**
     * @return Collection<int, Offer>
     */
    public function findByBuyer(string $organizationId, string $buyerId): Collection
    {
        return $this->ordered([
            'organizationId' => $organizationId,
            'buyerId' => $buyerId,
        ]);
    }

    public function findById(string $id): ?Offer
    {
        $found = $this->findByPK($id);

        return $found instanceof Offer ? $found : null;
    }
}
