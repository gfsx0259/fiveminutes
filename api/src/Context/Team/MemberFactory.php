<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\User\User;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class MemberFactory
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
    ) {}

    public function create(User $account, ?string $unitId, string $email, Permissions $permissions): Member
    {
        $now = $this->clock->now();

        return new Member(
            $this->uuids->uuid4()->toString(),
            $account->getOrganizationId(),
            $account->getId(),
            $unitId,
            $email,
            $permissions,
            null,
            $now->modify(sprintf('+%d days', Member::INVITE_DAYS)),
            $now,
            $now,
        );
    }
}
