<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Value\Money;
use App\Value\Volume;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;

#[Entity(repository: AcceptanceRepository::class, table: 'acceptances')]
class Acceptance
{
    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'slot_id', nullable: true)]
        private readonly ?string $slotId,
        #[Column(type: 'uuid', name: 'demand_id', nullable: true)]
        private readonly ?string $demandId,
        #[Column(type: 'uuid', name: 'partner_id')]
        private readonly string $partnerId,
        #[Column(type: 'uuid', name: 'buyer_id')]
        private readonly string $buyerId,
        #[Column(type: 'integer', typecast: 'int')]
        private readonly int $volume,
        #[Column(type: 'integer', name: 'buyer_price_cents', typecast: 'int')]
        private readonly int $buyerPriceCents,
        #[Column(type: 'integer', name: 'partner_price_cents', typecast: 'int')]
        private readonly int $partnerPriceCents,
        #[Column(type: 'string', name: 'pricing_model')]
        private readonly string $pricingModel,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'integer', name: 'sold_count', typecast: 'int')]
        private int $soldCount = 0,
    ) {
        if (($this->slotId === null) === ($this->demandId === null)) {
            throw new InvalidArgumentException('Acceptance refers to a slot or a demand');
        }

        new Volume($this->volume);
        $buyerPrice = new Money($this->buyerPriceCents);
        $partnerPrice = new Money($this->partnerPriceCents);
        if ($partnerPrice->isBelow($buyerPrice)) {
            throw new InvalidArgumentException('Partner price is below the buyer price');
        }
        PricingModel::from($this->pricingModel);
        if ($this->soldCount < 0 || $this->soldCount > $this->volume) {
            throw new InvalidArgumentException('Sold count is outside the acceptance volume');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getSlotId(): ?string
    {
        return $this->slotId;
    }

    public function getDemandId(): ?string
    {
        return $this->demandId;
    }

    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    public function getBuyerId(): string
    {
        return $this->buyerId;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function getBuyerPriceCents(): int
    {
        return $this->buyerPriceCents;
    }

    public function getPartnerPriceCents(): int
    {
        return $this->partnerPriceCents;
    }

    public function getPricingModel(): PricingModel
    {
        return PricingModel::from($this->pricingModel);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getSoldCount(): int
    {
        return $this->soldCount;
    }

    public function hasRoom(): bool
    {
        return $this->soldCount < $this->volume;
    }

    public function reserve(): void
    {
        if (!$this->hasRoom()) {
            throw new DomainException('Acceptance has no remaining volume');
        }

        $this->soldCount++;
    }
}
