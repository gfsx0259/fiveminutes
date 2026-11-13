<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;

final readonly class CabinetListings
{
    public function __construct(
        private SlotRepository $slots,
        private DemandRepository $demands,
        private AcceptanceRepository $acceptances,
        private UserRepository $users,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function slots(User $user): array
    {
        $organizationId = $user->getOrganizationId();
        $found = [];
        $source = $user->getRole() === UserRole::Buyer
            ? $this->slots->findByBuyer($organizationId, $user->getId())
            : $this->slots->findOpenByOrganization($organizationId);
        foreach ($source as $slot) {
            $found[$slot->getId()] = $slot;
        }

        if ($user->getRole() === UserRole::Partner) {
            foreach ($this->acceptances->findByPartner($user->getId()) as $acceptance) {
                $slotId = $acceptance->getSlotId();
                if ($slotId === null || isset($found[$slotId])) {
                    continue;
                }
                $slot = $this->slots->findById($slotId);
                if ($slot !== null && $slot->getOrganizationId() === $organizationId) {
                    $found[$slotId] = $slot;
                }
            }
        }

        $emails = $this->emails($organizationId);
        $rows = [];
        foreach ($this->newest($found) as $slot) {
            $rows[] = [
                'id' => $slot->getId(),
                'geo' => $slot->getGeo(),
                'volume' => $slot->getVolume(),
                'priceCents' => $slot->getPriceCents(),
                'pricingModel' => $slot->getPricingModel()->value,
                'deadline' => $slot->getDeadline()->format('Y-m-d'),
                'status' => $slot->getStatus()->value,
                'buyerId' => $slot->getBuyerId(),
                'buyerEmail' => $emails[$slot->getBuyerId()] ?? '',
                'createdAt' => $slot->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function demands(User $user): array
    {
        $organizationId = $user->getOrganizationId();
        $found = [];
        $source = $user->getRole() === UserRole::Partner
            ? $this->demands->findByPartner($organizationId, $user->getId())
            : $this->demands->findOpenByOrganization($organizationId);
        foreach ($source as $demand) {
            $found[$demand->getId()] = $demand;
        }

        if ($user->getRole() === UserRole::Buyer) {
            foreach ($this->acceptances->findByBuyer($user->getId()) as $acceptance) {
                $demandId = $acceptance->getDemandId();
                if ($demandId === null || isset($found[$demandId])) {
                    continue;
                }
                $demand = $this->demands->findById($demandId);
                if ($demand !== null && $demand->getOrganizationId() === $organizationId) {
                    $found[$demandId] = $demand;
                }
            }
        }

        $emails = $this->emails($organizationId);
        $rows = [];
        foreach ($this->newest($found) as $demand) {
            $rows[] = [
                'id' => $demand->getId(),
                'geo' => $demand->getGeo(),
                'volume' => $demand->getVolume(),
                'priceCents' => $demand->getPriceCents(),
                'pricingModel' => $demand->getPricingModel()->value,
                'serviceOn' => $demand->getServiceOn()->format('Y-m-d'),
                'opensAt' => $demand->getOpensAt(),
                'closesAt' => $demand->getClosesAt(),
                'status' => $demand->getStatus()->value,
                'partnerId' => $demand->getPartnerId(),
                'partnerEmail' => $emails[$demand->getPartnerId()] ?? '',
                'createdAt' => $demand->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, Slot|Demand> $found
     * @return list<Slot|Demand>
     */
    private function newest(array $found): array
    {
        $rows = array_values($found);
        usort($rows, static fn (Slot|Demand $left, Slot|Demand $right): int => $right->getCreatedAt() <=> $left->getCreatedAt());

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function emails(string $organizationId): array
    {
        $emails = [];
        foreach ($this->users->findByOrganization($organizationId) as $user) {
            $emails[$user->getId()] = $user->getEmail();
        }

        return $emails;
    }
}
