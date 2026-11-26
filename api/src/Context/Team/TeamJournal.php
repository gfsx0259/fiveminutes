<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\User\User;
use Cycle\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class TeamJournal
{
    private const int LIMIT = 200;

    public function __construct(
        private TeamEventRepository $events,
        private UuidFactoryInterface $uuids,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    /**
     * @param array<string, scalar|null> $details
     */
    public function record(User $account, string $actor, TeamAction $action, ?string $unitId, array $details = []): void
    {
        $this->entityManager->persist(new TeamEvent(
            $this->uuids->uuid4()->toString(),
            $account->getId(),
            $unitId,
            $actor,
            $action->value,
            $details,
            $this->clock->now(),
        ));
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public function log(User $account, string $actor, TeamAction $action, ?string $unitId, array $details = []): void
    {
        $this->record($account, $actor, $action, $unitId, $details);
        $this->entityManager->run();
    }

    /**
     * @return list<TeamEvent>
     */
    public function latest(Seat $seat, TeamTree $tree): array
    {
        if (!$seat->manages()) {
            throw new NotTeamLeadException('Only a lead sees the team activity');
        }

        $unitIds = $seat->unitId() === null
            ? null
            : array_map(static fn (TeamUnit $unit): string => $unit->getId(), $tree->within($seat->unitId()));

        return $this->events->latest($seat->getAccount()->getId(), $unitIds, self::LIMIT);
    }
}
