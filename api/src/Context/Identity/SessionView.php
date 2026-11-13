<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\Organization\OrganizationRepository;
use App\Context\User\UserRepository;

final class SessionView
{
    public function __construct(
        private UserRepository $users,
        private OrganizationRepository $organizations,
    ) {}

    /**
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    public function present(array $session): array
    {
        $identity = $session['identity'] ?? null;
        $identityId = is_array($identity) && is_string($identity['id'] ?? null) ? $identity['id'] : '';
        $user = $identityId !== '' ? $this->users->findByIdentityId($identityId) : null;
        $organization = $user !== null ? $this->organizations->findById($user->getOrganizationId()) : null;

        return [
            'session' => $session,
            'user' => $user === null ? null : [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value,
                'organization' => $organization === null ? null : [
                    'id' => $organization->getId(),
                    'name' => $organization->getName(),
                    'apex' => $organization->getApex(),
                ],
            ],
        ];
    }
}
