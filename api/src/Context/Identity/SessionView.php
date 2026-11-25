<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\Organization\OrganizationRepository;
use App\Context\Team\Permission;
use App\Context\Team\Seat;
use App\Context\Team\TeamBudget;
use App\Context\Team\TeamUnit;
use App\Context\Team\TeamUnitRepository;
use App\Context\User\UserRole;
use App\Context\User\UserRepository;
use Ory\Client\Model\Session;

final readonly class SessionView
{
    public function __construct(
        private UserRepository $users,
        private OrganizationRepository $organizations,
        private TeamUnitRepository $teams,
        private TeamBudget $budget,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Session $session, Seat $seat): array
    {
        $user = $seat->getAccount();
        $actor = $seat->getImpersonator();
        $member = $seat->getMember();
        $organization = $this->organizations->findById($user->getOrganizationId());

        $impersonation = null;
        if ($actor !== null) {
            $impersonation = [
                'active' => true,
                'actor' => [
                    'id' => $actor->getId(),
                    'email' => $actor->getEmail(),
                ],
            ];
        }

        return [
            'session' => $session->jsonSerialize(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value,
                'balanceCents' => $user->getRole() === UserRole::Admin || !$seat->can(Permission::WalletView) || $seat->unitId() !== null
                    ? null
                    : $user->getBalance()->cents(),
                'permissions' => $seat->permissions()->values(),
                'organization' => $organization === null ? null : [
                    'id' => $organization->getId(),
                    'name' => $organization->getName(),
                    'apex' => $organization->getApex(),
                    'markupPercent' => $organization->getMarkupPercent(),
                    'shelfAgePolicy' => $organization->shelfAgePolicyArray(),
                    'rotationAxes' => $organization->rotationAxesList(),
                    'balanceCents' => $user->getRole() === UserRole::Admin
                        ? $organization->getBalance()->cents()
                        : null,
                ],
            ],
            'member' => $member === null ? null : [
                'id' => $member->getId(),
                'email' => $member->getEmail(),
                'teamId' => $member->getUnitId(),
                'teamPath' => array_map(
                    static fn (TeamUnit $unit): string => $unit->getName(),
                    $this->teams->treeOf($user->getId())->path($member->getUnitId()),
                ),
            ],
            'budget' => $member !== null && $seat->can(Permission::WalletView) ? $this->budget->nearest($seat) : null,
            'impersonation' => $impersonation,
        ];
    }
}
