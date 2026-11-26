<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: OrderPlacementRepository::class, table: 'team_orders')]
class OrderPlacement
{
    public function __construct(
        #[Column(type: 'uuid', name: 'order_id', primary: true)]
        private readonly string $orderId,
        #[Column(type: 'uuid', name: 'account_id')]
        private readonly string $accountId,
        #[Column(type: 'uuid', name: 'unit_id', nullable: true)]
        private ?string $unitId,
        #[Column(type: 'string', name: 'opened_by')]
        private readonly string $openedBy,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
        private DateTimeInterface $updatedAt,
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function getOpenedBy(): string
    {
        return $this->openedBy;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function moveTo(?string $unitId, Clock $clock): void
    {
        $this->unitId = $unitId;
        $this->updatedAt = $clock->now();
    }
}
