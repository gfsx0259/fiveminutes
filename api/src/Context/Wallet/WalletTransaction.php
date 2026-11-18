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

    #[Column(type: 'string')]
    private readonly string $kind;

    /**
     * @var array<string, mixed>
     */
    #[Column(type: 'jsonb', typecast: 'json')]
    private readonly array $metadata;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'user_id', nullable: true)]
        private readonly ?string $userId,
        Direction $direction,
        #[Column(type: 'integer', name: 'amount_cents', typecast: 'int')]
        private readonly int $amountCents,
        TransactionKind $kind,
        #[Column(type: 'uuid', name: 'lead_uuid', nullable: true)]
        private readonly ?string $leadUuid,
        #[Column(type: 'uuid', name: 'order_id', nullable: true)]
        private readonly ?string $orderId,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        array $metadata = [],
    ) {
        $this->direction = $direction->value;
        $this->kind = $kind->value;
        $this->metadata = $metadata;
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

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getDirection(): Direction
    {
        return Direction::from($this->direction);
    }

    public function getKind(): TransactionKind
    {
        return TransactionKind::from($this->kind);
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getLeadUuid(): ?string
    {
        return $this->leadUuid;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}