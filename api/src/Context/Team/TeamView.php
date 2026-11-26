<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;

final readonly class TeamView
{
    public function __construct(
        private TeamDesk $desk,
        private TeamBudget $budget,
        private TeamLinks $links,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function roster(Seat $seat): array
    {
        $account = $seat->getAccount();
        $tree = $this->desk->tree($seat);
        $signUpUrl = $this->links->signUpUrl($account);
        $money = $seat->can(Permission::WalletView) || $seat->manages();
        $usage = $money ? $this->budget->usage($account, $tree) : [];

        return [
            'viewer' => [
                'teamId' => $seat->unitId(),
                'owner' => $seat->isOwner(),
                'realOwner' => $seat->isOwner() && $seat->getImpersonator() === null,
                'lead' => $seat->manages(),
                'canBuild' => $this->desk->buildsIn($seat, $tree, $seat->unitId()),
                'path' => array_map(
                    static fn (TeamUnit $unit): array => ['id' => $unit->getId(), 'name' => $unit->getName()],
                    $tree->path($seat->unitId()),
                ),
            ],
            'owner' => ['email' => $account->getEmail()],
            'teams' => array_map(
                fn (TeamUnit $unit): array => $this->team($seat, $tree, $unit, $money ? ($usage[$unit->getId()] ?? null) : null),
                $tree->within($seat->unitId()),
            ),
            'members' => array_map(
                fn (Member $member): array => $this->member($member, $signUpUrl, $this->desk->managesMember($seat, $tree, $member)),
                $this->desk->members($seat, $tree),
            ),
            'permissions' => array_map(
                static fn (Permission $permission): array => [
                    'id' => $permission->value,
                    'section' => $permission->section(),
                    'label' => $permission->label(),
                    'description' => $permission->description(),
                ],
                Permission::cases(),
            ),
            'roles' => array_map(
                static fn (TeamRole $role): array => [
                    'id' => $role->value,
                    'label' => $role->label(),
                    'description' => $role->description(),
                    'permissions' => $role->permissions()->values(),
                ],
                TeamRole::cases(),
            ),
            'periods' => array_map(
                static fn (BudgetPeriod $period): array => ['id' => $period->value, 'label' => $period->label()],
                BudgetPeriod::cases(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function member(Member $member, string $signUpUrl, bool $manageable = true): array
    {
        $now = $this->clock->now();

        return [
            'id' => $member->getId(),
            'email' => $member->getEmail(),
            'teamId' => $member->getUnitId(),
            'permissions' => $member->getPermissions()->values(),
            'role' => $this->role($member->getPermissions())?->value,
            'status' => $member->hasJoined() ? 'active' : ($member->isExpired($now) ? 'expired' : 'invited'),
            'invitedAt' => $member->getCreatedAt()->format(DATE_ATOM),
            'expiresAt' => $member->getExpiresAt()?->format(DATE_ATOM),
            'signUpUrl' => $member->hasJoined() || !$manageable ? null : $signUpUrl,
            'manageable' => $manageable,
        ];
    }

    /**
     * @param array{spentCents: int, reservedCents: int}|null $usage
     * @return array<string, mixed>
     */
    public function team(Seat $seat, TeamTree $tree, TeamUnit $unit, ?array $usage): array
    {
        $budget = $usage === null ? null : $this->budget->present($unit, $usage);

        return [
            'id' => $unit->getId(),
            'parentId' => $unit->getParentId(),
            'name' => $unit->getName(),
            'budget' => $budget,
            'manageable' => $this->desk->managesUnit($seat, $tree, $unit->getId()),
            'canBuild' => $this->desk->buildsIn($seat, $tree, $unit->getId()),
        ];
    }

    /**
     * @param list<BudgetChange> $changes
     * @return list<array<string, mixed>>
     */
    public function budgetHistory(array $changes): array
    {
        return array_map(
            static fn (BudgetChange $change): array => [
                'budgetCents' => $change->getBudgetCents(),
                'period' => $change->getBudgetPeriod()->value,
                'changedBy' => $change->getChangedBy(),
                'changedAt' => $change->getCreatedAt()->format(DATE_ATOM),
            ],
            $changes,
        );
    }

    /**
     * @param list<TeamEvent> $events
     * @return list<array<string, mixed>>
     */
    public function events(array $events): array
    {
        return array_map(
            static fn (TeamEvent $event): array => [
                'id' => $event->getId(),
                'action' => $event->getAction()->value,
                'label' => $event->getAction()->label(),
                'actor' => $event->getActor(),
                'teamId' => $event->getUnitId(),
                'details' => $event->getDetails(),
                'at' => $event->getCreatedAt()->format(DATE_ATOM),
            ],
            $events,
        );
    }

    public function signUpUrl(Seat $seat): string
    {
        return $this->links->signUpUrl($seat->getAccount());
    }

    private function role(Permissions $permissions): ?TeamRole
    {
        foreach (TeamRole::cases() as $role) {
            if ($role->matches($permissions)) {
                return $role;
            }
        }

        return null;
    }
}
