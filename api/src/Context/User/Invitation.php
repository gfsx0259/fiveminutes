<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Context\Organization\Organization;
use Cycle\ORM\EntityManagerInterface;
use DomainException;

final class Invitation
{
    public function __construct(
        private InviteRepository $invites,
        private UserRepository $users,
        private InviteFactory $factory,
        private EntityManagerInterface $entityManager,
    ) {}

    public function open(Organization $organization, string $email, UserRole $role): Invite
    {
        $existing = $this->invites->findByEmail($email);
        if ($existing !== null) {
            $same = $existing->isActive()
                && $existing->getOrganizationId() === $organization->getId()
                && $existing->getRole() === $role;
            if ($same) {
                return $existing;
            }

            throw new DomainException('Invite already exists for this email');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new DomainException('User already exists');
        }

        $invite = $this->factory->create($organization, $email, $role);
        $this->entityManager->persist($invite);
        $this->entityManager->run();

        return $invite;
    }
}
