<?php

declare(strict_types=1);

namespace App\Context\Market;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final readonly class Book
{
    public function __construct(
        private AcceptanceRepository $acceptances,
        private SlotRepository $slots,
        private DemandRepository $demands,
    ) {}

    public function choose(string $organizationId, string $buyerId, string $geo, DateTimeImmutable $now): ?Acceptance
    {
        $chosen = null;
        foreach ($this->acceptances->findByBuyer($buyerId) as $acceptance) {
            if (!$this->fits($acceptance, $organizationId, $geo, $now)) {
                continue;
            }
            if ($chosen === null || $this->beats($acceptance, $chosen)) {
                $chosen = $acceptance;
            }
        }

        return $chosen;
    }

    private function fits(Acceptance $acceptance, string $organizationId, string $geo, DateTimeImmutable $now): bool
    {
        if ($acceptance->getOrganizationId() !== $organizationId || !$acceptance->hasRoom()) {
            return false;
        }

        $slotId = $acceptance->getSlotId();
        if ($slotId !== null) {
            $slot = $this->slots->findByPK($slotId);

            return $slot instanceof Slot && $this->slotFits($slot, $geo, $now);
        }

        $demandId = $acceptance->getDemandId();
        if ($demandId === null) {
            return false;
        }
        $demand = $this->demands->findByPK($demandId);

        return $demand instanceof Demand && $this->demandFits($demand, $geo, $now);
    }

    private function slotFits(Slot $slot, string $geo, DateTimeImmutable $now): bool
    {
        return $slot->getStatus() === ListingStatus::Taken
            && $slot->getGeo() === strtoupper($geo)
            && $slot->getDeadline() >= $now;
    }

    private function demandFits(Demand $demand, string $geo, DateTimeImmutable $now): bool
    {
        $clock = $now->setTimezone(new DateTimeZone('UTC'))->format('H:i');
        $today = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');

        return $demand->getStatus() === ListingStatus::Taken
            && $demand->getGeo() === strtoupper($geo)
            && $demand->getServiceOn()->format('Y-m-d') === $today
            && $clock >= $demand->getOpensAt()
            && $clock < $demand->getClosesAt();
    }

    private function beats(Acceptance $candidate, Acceptance $current): bool
    {
        if ($candidate->getBuyerPriceCents() !== $current->getBuyerPriceCents()) {
            return $candidate->getBuyerPriceCents() > $current->getBuyerPriceCents();
        }

        return $this->stamp($candidate->getCreatedAt()) < $this->stamp($current->getCreatedAt());
    }

    private function stamp(DateTimeInterface $at): int
    {
        return $at->getTimestamp();
    }
}
