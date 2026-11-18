<?php

declare(strict_types=1);

namespace App\Context\Lead;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use InvalidArgumentException;

#[Entity(repository: PlacementRepository::class, table: 'lead_placements')]
class Placement
{
    public function __construct(
        #[Column(type: 'uuid', primary: true, name: 'lead_uuid')]
        private readonly string $leadUuid,
        #[Column(type: 'uuid', name: 'order_id', nullable: true)]
        private readonly ?string $orderId,
        #[Column(type: 'uuid', name: 'seller_id', nullable: true)]
        private readonly ?string $sellerId,
        #[Column(type: 'integer', name: 'price_cents', typecast: 'int')]
        private readonly int $priceCents,
        #[Column(type: 'datetime', name: 'sold_at', nullable: true, typecast: 'datetime')]
        private readonly ?DateTimeInterface $soldAt,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {
        if ($this->priceCents < 0) {
            throw new InvalidArgumentException('Price must be zero or positive');
        }
        $sold = $this->orderId !== null;
        if ($sold !== ($this->sellerId !== null) || $sold !== ($this->soldAt !== null) || $sold !== ($this->priceCents > 0)) {
            throw new InvalidArgumentException('Placement must be sold or stocked as a whole');
        }
    }

    public function getLeadUuid(): string
    {
        return $this->leadUuid;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getSellerId(): ?string
    {
        return $this->sellerId;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getSoldAt(): ?DateTimeInterface
    {
        return $this->soldAt;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
