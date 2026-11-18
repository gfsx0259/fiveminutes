<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<Preset>
 */
class PresetRepository extends Repository
{
    /**
     * @return Collection<int, Preset>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        return $this->ordered(['organizationId' => $organizationId]);
    }

    /**
     * @return Collection<int, Preset>
     */
    public function matching(
        string $organizationId,
        string $country,
        string $trafficSource,
        string $age,
        string $gender,
    ): Collection {
        return $this->ordered([
            'organizationId' => $organizationId,
            'country' => $country,
            'trafficSource' => $trafficSource,
            'age' => $age,
            'gender' => $gender,
        ]);
    }

    public function findById(string $id): ?Preset
    {
        $found = $this->findByPK($id);

        return $found instanceof Preset ? $found : null;
    }
}
