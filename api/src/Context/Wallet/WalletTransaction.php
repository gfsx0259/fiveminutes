<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Value\Money;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: WalletTransactionRepository::class, table: 'wallet_transactions')]
class WalletTransaction
{
    #[Column(type: 'uuid', primary: true)]
    private string $id;

    #[Column(type: 'uuid', name: 'organization_id')]
    private string $organizationId;

    #[Column(type: 'uuid', name: 'user_id')]
    private string $userId;

    #[Column(type: 'string')]
    private string $direction;

    #[Column(type: 'integer', name: 'amount_cents', typecast: 'int')]
    private int $amountCents;

    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    private DateTimeInterface $createdAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $userId,
        Direction $direction,
        int $amountCents,
        DateTimeInterface $createdAt,
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->userId = $userId;
        $this->direction = $direction->value;
        $this->amountCents = (new Money($amountCents))->cents();
        $this->createdAt = $createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getDirection(): Direction
    {
        return Direction::from($this->direction);
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
