<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Orm\Repository;
use Doctrine\Common\Collections\Collection;

/**
 * @extends Repository<User>
 */
class UserRepository extends Repository
{
    public function findByEmail(string $email): ?User
    {
        $found = $this->findOne(['email' => strtolower(trim($email))]);

        return $found instanceof User ? $found : null;
    }

    public function findByIdentityId(string $identityId): ?User
    {
        $found = $this->findOne(['identityId' => $identityId]);

        return $found instanceof User ? $found : null;
    }

    /**
     * @return Collection<int, User>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        return $this->collection(['organizationId' => $organizationId]);
    }
}
