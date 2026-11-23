<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Organization\OrganizationRepository;

final readonly class OrganizationShelfAge
{
    public function __construct(private OrganizationRepository $organizations) {}

    public function forOrganization(string $organizationId): ShelfAgePolicy
    {
        $organization = $this->organizations->findById($organizationId);
        if ($organization === null) {
            return ShelfAgePolicy::default();
        }

        return $organization->getShelfAgePolicy();
    }
}