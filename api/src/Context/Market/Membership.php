<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\User\User;
use App\Context\User\UserRole;
use DomainException;

final readonly class Membership
{
    public function assertRole(User $user, UserRole $role, string $organizationId): void
    {
        if ($user->getOrganizationId() !== $organizationId) {
            throw new DomainException('User is outside the organization');
        }

        if ($user->getRole() !== $role) {
            throw new DomainException('User role does not match');
        }
    }
}
