<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\Team\Member;
use App\Context\Team\MemberRepository;
use App\Context\Team\Permission;
use App\Context\Team\Seat;
use App\Context\Team\TeamDesk;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use Ory\Client\Model\Identity;

final readonly class CurrentUser
{
    public function __construct(
        private KratosSessions $sessions,
        private UserRepository $users,
        private MemberRepository $members,
        private TeamDesk $team,
        private ImpersonationCookie $impersonationCookie,
    ) {}

    public function require(string $cookie, ?Permission $permission = null): User
    {
        $seat = $this->seat($cookie);
        if ($permission !== null) {
            $seat->authorize($permission);
        }

        return $seat->getAccount();
    }

    public function seat(string $cookie): Seat
    {
        $identity = $this->identity($cookie);
        $identityId = (string) $identity->getId();
        $user = $this->users->findByIdentityId($identityId);
        if ($user !== null) {
            $effective = $this->effective($user, $cookie);

            return new Seat($effective, null, $effective->getId() === $user->getId() ? null : $user);
        }

        $member = $this->members->findByIdentityId($identityId) ?? $this->invitedMember($identity);
        $account = $member === null ? null : $this->users->findByPK($member->getAccountId());
        if (!$account instanceof User) {
            throw new NoAccountException();
        }

        return new Seat($account, $member);
    }

    public function requireActor(string $cookie): User
    {
        $user = $this->users->findByIdentityId((string) $this->identity($cookie)->getId());
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

    private function identity(string $cookie): Identity
    {
        $identity = $this->sessions->read($cookie)->getIdentity();
        if ($identity === null || (string) $identity->getId() === '') {
            throw new NoAccountException();
        }

        return $identity;
    }

    private function invitedMember(Identity $identity): ?Member
    {
        $email = (new IdentityEmail($identity))->value();

        return $email === null ? null : $this->team->join((string) $identity->getId(), $email);
    }
}
