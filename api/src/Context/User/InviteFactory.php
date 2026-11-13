<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use App\Context\Organization\Organization;
use Ramsey\Uuid\UuidFactoryInterface;
use App\Value\Email;

final readonly class InviteFactory
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
    ) {}

    public function create(Organization $organization, string $email, UserRole $role): Invite
    {
        $now = $this->clock->now();

        return new Invite(
            $this->uuids->uuid4()->toString(),
            $organization->getId(),
            (new Email($email))->toString(),
            $role,
            $now,
            $now,
        );
    }
}
