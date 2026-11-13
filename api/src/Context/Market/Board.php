<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use App\Context\Organization\Organization;
use App\Context\User\User;
use App\Context\User\UserRole;
use Ramsey\Uuid\UuidFactoryInterface;
use App\Value\Money;
use DateTimeInterface;

final readonly class Board
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
        private Markup $markup,
        private Membership $membership,
        private ListingTransition $transitions,
    ) {}

    public function openSlot(
        Organization $organization,
        User $buyer,
        string $geo,
        int $volume,
        int $priceCents,
        PricingModel $pricingModel,
        DateTimeInterface $deadline,
    ): Slot {
        $this->membership->assertRole($buyer, UserRole::Buyer, $organization->getId());
        $now = $this->clock->now();

        return new Slot(
            $this->uuids->uuid4()->toString(),
            $organization->getId(),
            $buyer->getId(),
            $geo,
            $volume,
            $priceCents,
            $pricingModel->value,
            $deadline,
            ListingStatus::Open->value,
            $now,
            $now,
        );
    }

    public function openDemand(
        Organization $organization,
        User $partner,
        string $geo,
        int $volume,
        int $priceCents,
        PricingModel $pricingModel,
        DateTimeInterface $serviceOn,
        string $opensAt,
        string $closesAt,
    ): Demand {
        $this->membership->assertRole($partner, UserRole::Partner, $organization->getId());
        $now = $this->clock->now();

        return new Demand(
            $this->uuids->uuid4()->toString(),
            $organization->getId(),
            $partner->getId(),
            $geo,
            $volume,
            $priceCents,
            $pricingModel->value,
            $serviceOn,
            $opensAt,
            $closesAt,
            ListingStatus::Open->value,
            $now,
            $now,
        );
    }

    public function takeSlot(Slot $slot, User $partner): Acceptance
    {
        $this->membership->assertRole($partner, UserRole::Partner, $slot->getOrganizationId());
        $acceptance = $this->acceptance(
            $slot->getOrganizationId(),
            $slot->getId(),
            null,
            $partner->getId(),
            $slot->getBuyerId(),
            $slot->getVolume(),
            $slot->getPriceCents(),
            $slot->getPricingModel(),
        );
        $slot->markTaken($this->transitions);

        return $acceptance;
    }

    public function takeDemand(Demand $demand, User $buyer): Acceptance
    {
        $this->membership->assertRole($buyer, UserRole::Buyer, $demand->getOrganizationId());
        $acceptance = $this->acceptance(
            $demand->getOrganizationId(),
            null,
            $demand->getId(),
            $demand->getPartnerId(),
            $buyer->getId(),
            $demand->getVolume(),
            $demand->getPriceCents(),
            $demand->getPricingModel(),
        );
        $demand->markTaken($this->transitions);

        return $acceptance;
    }

    public function closeSlot(Slot $slot, User $actor): void
    {
        $this->membership->assertRole($actor, UserRole::Buyer, $slot->getOrganizationId());
        $slot->close($actor, $this->transitions);
    }

    public function closeDemand(Demand $demand, User $actor): void
    {
        $this->membership->assertRole($actor, UserRole::Partner, $demand->getOrganizationId());
        $demand->close($actor, $this->transitions);
    }

    private function acceptance(
        string $organizationId,
        ?string $slotId,
        ?string $demandId,
        string $partnerId,
        string $buyerId,
        int $volume,
        int $buyerPriceCents,
        PricingModel $pricingModel,
    ): Acceptance {
        $buyerPrice = new Money($buyerPriceCents);

        return new Acceptance(
            $this->uuids->uuid4()->toString(),
            $organizationId,
            $slotId,
            $demandId,
            $partnerId,
            $buyerId,
            $volume,
            $buyerPrice->cents(),
            $this->markup->partnerPays($buyerPrice)->cents(),
            $pricingModel->value,
            $this->clock->now(),
        );
    }
}
