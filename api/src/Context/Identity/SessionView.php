<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\Organization\OrganizationRepository;
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
    public function present(Session $session): array
    {
        $identity = $session->getIdentity();
        $identityId = $identity === null ? '' : (string) $identity->getId();
        $user = $identityId !== '' ? $this->users->findByIdentityId($identityId) : null;
        $organization = $user !== null ? $this->organizations->findById($user->getOrganizationId()) : null;

        return [
            'session' => $session->jsonSerialize(),
            'user' => $user === null ? null : [
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
                    'balanceCents' => $user->getRole() === UserRole::Admin
                        ? $organization->getBalance()->cents()
                        : null,
                ],
            ],
        ];
    }
}
