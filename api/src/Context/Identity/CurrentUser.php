<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\User\User;
use App\Context\User\UserRepository;

final readonly class CurrentUser
{
    public function __construct(
        private KratosSessions $sessions,
        private UserRepository $users,
    ) {}

    public function require(string $cookie): User
    {
        $identity = $this->sessions->read($cookie)->getIdentity();
        $identityId = $identity === null ? '' : (string) $identity->getId();
        $user = $identityId !== '' ? $this->users->findByIdentityId($identityId) : null;
        if ($user === null) {
            throw new NoAccountException();
        }

        return $user;
    }
}
