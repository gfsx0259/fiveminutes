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
    #[Column(type: 'string')]
    private readonly string $direction;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'user_id')]
        private readonly string $userId,
        Direction $direction,
        #[Column(type: 'integer', name: 'amount_cents', typecast: 'int')]
        private readonly int $amountCents,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {
        $this->direction = $direction->value;
        new Money($this->amountCents);
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
