<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;

final readonly class CurrentUser
{
    public function __construct(
        private KratosSessions $sessions,
        private UserRepository $users,
        private ImpersonationCookie $impersonationCookie,
    ) {}

    public function require(string $cookie): User
    {
        $actor = $this->requireActor($cookie);

        return $this->effective($actor, $cookie);
    }

    public function requireActor(string $cookie): User
    {
        $identity = $this->sessions->read($cookie)->getIdentity();
        $identityId = $identity === null ? '' : (string) $identity->getId();
        $user = $identityId !== '' ? $this->users->findByIdentityId($identityId) : null;
        if ($user === null) {
            throw new NoAccountException();
        }

        return $user;
    }

    public function effective(User $actor, string $cookie): User
    {
        if ($actor->getRole() !== UserRole::Admin) {
            return $actor;
        }

        $targetId = $this->impersonationCookie->userIdFromCookieHeader($cookie);
        if ($targetId === null || $targetId === $actor->getId()) {
            return $actor;
        }

        $target = $this->users->findByPK($targetId);
        if (!$target instanceof User) {
            return $actor;
        }

        if ($target->getRole() === UserRole::Admin) {
            return $actor;
        }

        if ($target->getOrganizationId() !== $actor->getOrganizationId()) {
            return $actor;
        }

        return $target;
    }
}
