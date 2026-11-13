<?php

declare(strict_types=1);

namespace App\Context\User;

use App\Clock;
use App\Value\Balance;
use App\Value\Email;
use App\Value\Money;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: UserRepository::class, table: 'users')]
class User
{
    #[Column(type: 'uuid', primary: true)]
    private string $id;

    #[Column(type: 'uuid', name: 'organization_id')]
    private string $organizationId;

    #[Column(type: 'uuid', name: 'identity_id', nullable: true)]
    private ?string $identityId;

    #[Column(type: 'string')]
    private string $email;

    #[Column(type: 'string')]
    private string $role;

    #[Column(type: 'jsonb', typecast: 'json')]
    private array $meta;

    #[Column(type: 'integer', typecast: 'int')]
    private int $balance;

    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    private DateTimeInterface $createdAt;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $email,
        UserRole $role,
        ?string $identityId,
        array $meta,
        int $balance,
        DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->email = (new Email($email))->toString();
        $this->role = $role->value;
        $this->identityId = $identityId;
        $this->meta = $this->requireMeta($meta);
        $this->balance = (new Balance($balance))->cents();
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

    public function getIdentityId(): ?string
    {
        return $this->identityId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): UserRole
    {
        return UserRole::from($this->role);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function putMeta(string $key, mixed $value, Clock $clock): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new \InvalidArgumentException('Meta key is empty');
        }

        $meta = $this->meta;
        $meta[$key] = $value;
        $this->meta = $this->requireMeta($meta);
        $this->updatedAt = $clock->now();
    }

    public function getBalance(): Balance
    {
        return new Balance($this->balance);
    }

    public function credit(Money $amount, Clock $clock): void
    {
        $this->balance = $this->getBalance()->credit($amount)->cents();
        $this->updatedAt = $clock->now();
    }

    public function debit(Money $amount, Clock $clock): void
    {
        $this->balance = $this->getBalance()->debit($amount)->cents();
        $this->updatedAt = $clock->now();
    }

    public function linkIdentity(string $identityId, Clock $clock): void
    {
        if (trim($identityId) === '') {
            throw new \InvalidArgumentException('Identity id is empty');
        }

        $this->identityId = $identityId;
        $this->updatedAt = $clock->now();
    }

    /**
     * @param array<mixed, mixed> $meta
     * @return array<string, mixed>
     */
    private function requireMeta(array $meta): array
    {
        foreach (array_keys($meta) as $key) {
            if (!is_string($key) || trim($key) === '') {
                throw new \InvalidArgumentException('Meta key is empty');
            }
        }

        try {
            json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Meta is not valid JSON', 0, $exception);
        }

        return $meta;
    }
}
