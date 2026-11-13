<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use App\Context\Organization\OrganizationRepository;
use App\Value\Email;
use Cycle\ORM\EntityManagerInterface;

final class Registration
{
    public function __construct(
        private InviteRepository $invites,
        private UserRepository $users,
        private OrganizationRepository $organizations,
        private UserFactory $userFactory,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    public function accept(string $identityId, string $email): User
    {
        $identityId = trim($identityId);
        if ($identityId === '') {
            throw new \InvalidArgumentException('Identity id is empty');
        }

        $email = (new Email($email))->toString();
        $existing = $this->users->findByEmail($email);
        if ($existing !== null && $existing->getIdentityId() === $identityId) {
            return $existing;
        }
        if ($existing !== null && $existing->getIdentityId() !== null) {
            throw new IdentityAlreadyLinked('This email is already registered');
        }

        $byIdentity = $this->users->findByIdentityId($identityId);
        if ($byIdentity !== null && $byIdentity->getEmail() !== $email) {
            throw new IdentityAlreadyLinked('This identity is already linked');
        }

        $invite = $this->invites->findActiveByEmail($email);
        if ($invite === null) {
            throw new InviteMissing('На этот адрес нет приглашения');
        }

        $organization = $this->organizations->findById($invite->getOrganizationId());
        if ($organization === null) {
            throw new InviteMissing('Организация приглашения не найдена');
        }

        $user = $existing ?? $this->userFactory->create($organization, $email, $invite->getRole(), $identityId);
        if ($existing !== null) {
            $user->linkIdentity($identityId, $this->clock);
        }

        $invite->accept($this->clock);
        $this->entityManager->persist($invite);
        $this->entityManager->persist($user);
        $this->entityManager->run();

        return $user;
    }
}
