<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Value\Money;
use App\Value\Volume;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: AcceptanceRepository::class, table: 'acceptances')]
class Acceptance
{
    #[Column(type: 'uuid', primary: true)]
    private string $id;

    #[Column(type: 'uuid', name: 'organization_id')]
    private string $organizationId;

    #[Column(type: 'uuid', name: 'slot_id', nullable: true)]
    private ?string $slotId;

    #[Column(type: 'uuid', name: 'demand_id', nullable: true)]
    private ?string $demandId;

    #[Column(type: 'uuid', name: 'partner_id')]
    private string $partnerId;

    #[Column(type: 'uuid', name: 'buyer_id')]
    private string $buyerId;

    #[Column(type: 'integer', typecast: 'int')]
    private int $volume;

    #[Column(type: 'integer', name: 'buyer_price_cents', typecast: 'int')]
    private int $buyerPriceCents;

    #[Column(type: 'integer', name: 'partner_price_cents', typecast: 'int')]
    private int $partnerPriceCents;

    #[Column(type: 'string', name: 'pricing_model')]
    private string $pricingModel;

    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    private DateTimeInterface $createdAt;

    public function __construct(
        string $id,
        string $organizationId,
        ?string $slotId,
        ?string $demandId,
        string $partnerId,
        string $buyerId,
        int $volume,
        int $buyerPriceCents,
        int $partnerPriceCents,
        string $pricingModel,
        DateTimeInterface $createdAt,
    ) {
        if (($slotId === null) === ($demandId === null)) {
            throw new \InvalidArgumentException('Acceptance refers to a slot or a demand');
        }

        $buyerPrice = new Money($buyerPriceCents);
        $partnerPrice = new Money($partnerPriceCents);
        if ($partnerPrice->isBelow($buyerPrice)) {
            throw new \InvalidArgumentException('Partner price is below the buyer price');
        }

        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->slotId = $slotId;
        $this->demandId = $demandId;
        $this->partnerId = $partnerId;
        $this->buyerId = $buyerId;
        $this->volume = (new Volume($volume))->toInt();
        $this->buyerPriceCents = $buyerPrice->cents();
        $this->partnerPriceCents = $partnerPrice->cents();
        $this->pricingModel = PricingModel::from($pricingModel)->value;
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
}
