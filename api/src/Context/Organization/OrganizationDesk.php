<?php

declare(strict_types=1);

namespace App\Context\Organization;

use App\Clock;
use App\Context\Market\ShelfAgePolicy;
use App\Context\User\User;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;

final readonly class OrganizationDesk
{
    public function __construct(
        private OrganizationRepository $organizations,
        private Clock $clock,
        private EntityManagerInterface $entityManager,
    ) {}

    public function setMarkupPercent(User $admin, int $percent): Organization
    {
        if ($admin->getRole() !== UserRole::Admin) {
            throw new DomainException('Only an admin can change organization settings');
        }
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Markup percent must be between 0 and 100');
        }

        $organization = $this->organizations->findById($admin->getOrganizationId());
        if ($organization === null) {
            throw new DomainException('Organization not found');
        }

        $organization->setMarkupPercent($percent, $this->clock);
        $this->entityManager->persist($organization);
        $this->entityManager->run();

        return $organization;
    }

    public function shelfAgePolicy(User $user): ShelfAgePolicy
    {
        if ($user->getRole() !== UserRole::Admin && $user->getRole() !== UserRole::Partner) {
            throw new DomainException('Only organization members can read shelf age policy');
        }

        $organization = $this->organizations->findById($user->getOrganizationId());
        if ($organization === null) {
            throw new DomainException('Organization not found');
        }

        return $organization->getShelfAgePolicy();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setShelfAgePolicy(User $admin, array $payload): ShelfAgePolicy
    {
        if ($admin->getRole() !== UserRole::Admin) {
            throw new DomainException('Only an admin can change organization settings');
        }

        $organization = $this->organizations->findById($admin->getOrganizationId());
        if ($organization === null) {
            throw new DomainException('Organization not found');
        }

        $policy = ShelfAgePolicy::fromArray($payload);
        $organization->setShelfAgePolicy($policy->toArray(), $this->clock);
        $this->entityManager->persist($organization);
        $this->entityManager->run();

        return $policy;
    }
}