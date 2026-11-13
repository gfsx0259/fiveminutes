<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use App\Context\Organization\Organization;
use App\UuidGenerator;
use App\Value\Email;

final class InviteFactory
{
    public function __construct(
        private UuidGenerator $uuids,
        private Clock $clock,
    ) {}

    public function create(Organization $organization, string $email, UserRole $role): Invite
    {
        $now = $this->clock->now();

        return new Invite(
            $this->uuids->v4(),
            $organization->getId(),
            (new Email($email))->toString(),
            $role,
            $now,
            $now,
        );
    }
}
