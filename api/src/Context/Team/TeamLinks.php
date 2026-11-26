<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Context\Organization\OrganizationRepository;
use App\Context\User\User;
use App\Context\User\UserRole;

final readonly class TeamLinks
{
    public function __construct(
        private OrganizationRepository $organizations,
    ) {}

    public function signUpUrl(User $account): string
    {
        $apex = $this->organizations->findById($account->getOrganizationId())?->getApex() ?? '';
        $cabinet = sprintf('https://%s.%s/', UserRole::Partner->value, $apex);

        return sprintf('https://id.%s/auth/registration?return_to=%s', $apex, rawurlencode($cabinet));
    }

    public function organizationName(User $account): string
    {
        return $this->organizations->findById($account->getOrganizationId())?->getName() ?? 'Rider';
    }
}
