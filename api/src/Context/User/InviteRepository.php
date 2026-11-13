<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Orm\Repository;

/**
 * @extends Repository<Invite>
 */
class InviteRepository extends Repository
{
    public function findByEmail(string $email): ?Invite
    {
        $found = $this->findOne(['email' => strtolower(trim($email))]);

        return $found instanceof Invite ? $found : null;
    }

    public function findActiveByEmail(string $email): ?Invite
    {
        $invite = $this->findByEmail($email);

        return $invite !== null && $invite->isActive() ? $invite : null;
    }
}
