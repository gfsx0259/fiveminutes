<?php

declare(strict_types=1);

namespace App\Context\Organization;

use App\Orm\Repository;

/**
 * @extends Repository<Organization>
 */
class OrganizationRepository extends Repository
{
    public function findByApex(string $apex): ?Organization
    {
        $found = $this->findOne(['apex' => strtolower(trim($apex))]);

        return $found instanceof Organization ? $found : null;
    }

    public function findById(string $id): ?Organization
    {
        $found = $this->findByPK($id);

        return $found instanceof Organization ? $found : null;
    }
}
