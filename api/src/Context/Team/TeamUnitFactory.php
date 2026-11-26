<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\User\User;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class TeamUnitFactory
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
    ) {}

    public function create(User $account, ?string $parentId, string $name): TeamUnit
    {
        $now = $this->clock->now();

        return new TeamUnit(
            $this->uuids->uuid4()->toString(),
            $account->getOrganizationId(),
            $account->getId(),
            $parentId,
            $name,
            null,
            $now,
            $now,
        );
    }
}
