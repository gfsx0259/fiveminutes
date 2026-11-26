<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Context\User\User;

final readonly class Seat
{
    public function __construct(
        private User $account,
        private ?Member $member = null,
        private ?User $impersonator = null,
    ) {}

    public function getAccount(): User
    {
        return $this->account;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function getImpersonator(): ?User
    {
        return $this->impersonator;
    }

    public function isOwner(): bool
    {
        return $this->member === null;
    }

    public function unitId(): ?string
    {
        return $this->member?->getUnitId();
    }

    public function email(): string
    {
        return $this->member?->getEmail() ?? $this->impersonator?->getEmail() ?? $this->account->getEmail();
    }

    public function manages(): bool
    {
        return $this->isOwner() || $this->can(Permission::TeamManage);
    }

    public function permissions(): Permissions
    {
        return $this->member?->getPermissions() ?? new Permissions(Permission::cases());
    }

    public function can(Permission $permission): bool
    {
        return $this->permissions()->allows($permission);
    }

    public function authorize(Permission $permission): void
    {
        if (!$this->can($permission)) {
            throw new AccessDeniedException($permission);
        }
    }
}
