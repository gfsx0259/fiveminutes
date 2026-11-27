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
use InvalidArgumentException;
use JsonException;

#[Entity(repository: UserRepository::class, table: 'users')]
class User
{
    #[Column(type: 'string')]
    private string $email;

    #[Column(type: 'string')]
    private readonly string $role;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        string $email,
        UserRole $role,
        #[Column(type: 'uuid', name: 'identity_id', nullable: true)]
        private ?string $identityId,
        #[Column(type: 'jsonb', typecast: 'json')]
        private array $meta,
        #[Column(type: 'integer', typecast: 'int')]
        private int $balance,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
        private DateTimeInterface $updatedAt,
    ) {
        $this->email = (new Email($email))->toString();
        $this->role = $role->value;
        $this->requireMeta($this->meta);
        new Balance($this->balance);
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

    public function apiKey(): ?string
    {
        $key = $this->meta['apiKey'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * When the buyer's first test lead came through; null while the integration is still unproven.
     */
    public function integrationCompletedAt(): ?string
    {
        $at = $this->meta['integrationCompletedAt'] ?? null;

        return is_string($at) && $at !== '' ? $at : null;
    }

    public function completeIntegration(Clock $clock): void
    {
        if ($this->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        $this->putMeta('integrationCompletedAt', $clock->now()->format(DATE_ATOM), $clock);
    }

    public function issueApiKey(ApiKey $key, Clock $clock): void
    {
        if ($this->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        $this->putMeta('apiKey', $key->value(), $clock);
    }

    public function putMeta(string $key, mixed $value, Clock $clock): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new InvalidArgumentException('Meta key is empty');
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
            throw new InvalidArgumentException('Identity id is empty');
        }

        $this->identityId = $identityId;
        $this->updatedAt = $clock->now();
    }

    public function handOver(string $email, string $identityId, Clock $clock): void
    {
        $this->email = (new Email($email))->toString();
        $this->linkIdentity($identityId, $clock);
    }

    /**
     * @param array<mixed, mixed> $meta
     * @return array<string, mixed>
     */
    private function requireMeta(array $meta): array
    {
        foreach (array_keys($meta) as $key) {
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Meta key is empty');
            }
        }

        try {
            json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Meta is not valid JSON', 0, $exception);
        }

        return $meta;
    }
}
