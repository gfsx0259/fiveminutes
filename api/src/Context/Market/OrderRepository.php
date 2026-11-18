<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<Order>
 */
class OrderRepository extends Repository
{
    /**
     * @return Collection<int, Order>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        return $this->ordered(['organizationId' => $organizationId]);
    }

    /**
     * @return Collection<int, Order>
     */
    public function findByPartner(string $organizationId, string $partnerId): Collection
    {
        return $this->ordered([
            'organizationId' => $organizationId,
            'partnerId' => $partnerId,
        ]);
    }

    public function findById(string $id): ?Order
    {
        $found = $this->findByPK($id);

        return $found instanceof Order ? $found : null;
    }
}
