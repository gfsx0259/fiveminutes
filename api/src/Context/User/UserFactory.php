<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use App\Context\Organization\Organization;
use App\UuidGenerator;

final class UserFactory
{
    public function __construct(
        private UuidGenerator $uuids,
        private Clock $clock,
    ) {}

    public function create(Organization $organization, string $email, UserRole $role, ?string $identityId = null): User
    {
        $now = $this->clock->now();

        return new User(
            $this->uuids->v4(),
            $organization->getId(),
            $email,
            $role,
            $identityId,
            [],
            0,
            $now,
            $now,
        );
    }
}
