<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\Organization\OrganizationRepository;
use App\Context\User\User;
use App\Context\User\UserRole;
use App\Context\User\UserRepository;
use Ory\Client\Model\Session;

final readonly class SessionView
{
    public function __construct(
        private UserRepository $users,
        private OrganizationRepository $organizations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Session $session, User $user, ?User $actor = null): array
    {
        $organization = $this->organizations->findById($user->getOrganizationId());

        $impersonation = null;
        if ($actor !== null && $actor->getId() !== $user->getId()) {
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
                'balanceCents' => $user->getRole() === UserRole::Admin
                    ? null
                    : $user->getBalance()->cents(),
                'organization' => $organization === null ? null : [
                    'id' => $organization->getId(),
                    'name' => $organization->getName(),
                    'apex' => $organization->getApex(),
                    'markupPercent' => $organization->getMarkupPercent(),
                    'shelfAgePolicy' => $organization->shelfAgePolicyArray(),
                    'balanceCents' => $user->getRole() === UserRole::Admin
                        ? $organization->getBalance()->cents()
                        : null,
                ],
            ],
            'impersonation' => $impersonation,
        ];
    }
}
