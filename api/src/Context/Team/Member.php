<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\User\IdentityAlreadyLinkedException;
use App\Value\Email;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use InvalidArgumentException;

#[Entity(repository: MemberRepository::class, table: 'team_members')]
class Member
{
    public const int INVITE_DAYS = 7;

    #[Column(type: 'string')]
    private string $email;

    /** @var list<string> */
    #[Column(type: 'jsonb', typecast: 'json')]
    private array $permissions;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'account_id')]
        private readonly string $accountId,
        #[Column(type: 'uuid', name: 'unit_id', nullable: true)]
        private ?string $unitId,
        string $email,
        Permissions $permissions,
        #[Column(type: 'uuid', name: 'identity_id', nullable: true)]
        private ?string $identityId,
        #[Column(type: 'datetime', name: 'expires_at', nullable: true, typecast: 'datetime')]
        private ?DateTimeInterface $expiresAt,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
        private DateTimeInterface $updatedAt,
    ) {
        $this->email = (new Email($email))->toString();
        $this->permissions = $permissions->values();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getIdentityId(): ?string
    {
        return $this->identityId;
    }

    public function hasJoined(): bool
    {
        return $this->identityId !== null;
    }

    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function isExpired(DateTimeInterface $now): bool
    {
        return !$this->hasJoined() && $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    public function renew(Clock $clock): void
    {
        if ($this->hasJoined()) {
            throw new InvalidArgumentException('This person has already joined');
        }

        $this->expiresAt = $clock->now()->modify(sprintf('+%d days', self::INVITE_DAYS));
        $this->updatedAt = $clock->now();
    }

    public function getPermissions(): Permissions
    {
        return new Permissions($this->permissions);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function grant(Permissions $permissions, Clock $clock): void
    {
        $this->permissions = $permissions->values();
        $this->updatedAt = $clock->now();
    }

    public function moveTo(?string $unitId, Clock $clock): void
    {
        $this->unitId = $unitId;
        $this->updatedAt = $clock->now();
    }

    public function join(string $identityId, Clock $clock): void
    {
        if ($this->identityId !== null && $this->identityId !== $identityId) {
            throw new IdentityAlreadyLinkedException('This email is already registered');
        }

        $this->identityId = $identityId;
        $this->expiresAt = null;
        $this->updatedAt = $clock->now();
    }

    public function takeOver(string $email, string $identityId, Permissions $permissions, Clock $clock): void
    {
        $this->email = (new Email($email))->toString();
        $this->identityId = $identityId;
        $this->unitId = null;
        $this->permissions = $permissions->values();
        $this->expiresAt = null;
        $this->updatedAt = $clock->now();
    }
}
