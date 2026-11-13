<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: InviteRepository::class, table: 'invites')]
class Invite
{
    #[Column(type: 'uuid', primary: true)]
    private string $id;

    #[Column(type: 'uuid', name: 'organization_id')]
    private string $organizationId;

    #[Column(type: 'string')]
    private string $email;

    #[Column(type: 'string')]
    private string $role;

    #[Column(type: 'boolean', typecast: 'bool')]
    private bool $active;

    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    private DateTimeInterface $createdAt;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $email,
        UserRole $role,
        DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->email = $email;
        $this->role = $role->value;
        $this->active = true;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): UserRole
    {
        return UserRole::from($this->role);
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function accept(Clock $clock): void
    {
        $this->active = false;
        $this->updatedAt = $clock->now();
    }
}
