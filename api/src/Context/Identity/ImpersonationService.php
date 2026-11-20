<?php

declare(strict_types=1);

namespace App\Context\Identity;

use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use Psr\Log\LoggerInterface;

final readonly class ImpersonationService
{
    public function __construct(
        private UserRepository $users,
        private ImpersonationCookie $cookie,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws ImpersonationNotAllowedException
     */
    public function start(User $actor, string $targetUserId): User
    {
        if ($actor->getRole() !== UserRole::Admin) {
            throw ImpersonationNotAllowedException::notAdmin();
        }

        if ($targetUserId === $actor->getId()) {
            throw ImpersonationNotAllowedException::yourself();
        }

        $target = $this->users->findByPK($targetUserId);
        if (!$target instanceof User) {
            throw new NoAccountException();
        }

        if ($target->getRole() === UserRole::Admin) {
            throw ImpersonationNotAllowedException::admin();
        }

        if ($target->getOrganizationId() !== $actor->getOrganizationId()) {
            throw ImpersonationNotAllowedException::foreignOrganization();
        }

        $this->logger->info('Admin impersonation started', [
            'actor_id' => $actor->getId(),
            'actor_email' => $actor->getEmail(),
            'target_id' => $target->getId(),
            'target_email' => $target->getEmail(),
            'target_role' => $target->getRole()->value,
        ]);

        return $target;
    }

    public function stop(User $actor): void
    {
        $this->logger->info('Admin impersonation stopped', [
            'actor_id' => $actor->getId(),
            'actor_email' => $actor->getEmail(),
        ]);
    }
}