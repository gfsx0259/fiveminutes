<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Organization\Organization;
use App\Context\Organization\OrganizationRepository;
use App\Context\User\User;
use Cycle\ORM\EntityManagerInterface;
use DateTimeInterface;
use RuntimeException;

final readonly class Posting
{
    public function __construct(
        private Board $board,
        private OrganizationRepository $organizations,
        private SlotRepository $slots,
        private DemandRepository $demands,
        private EntityManagerInterface $entityManager,
    ) {}

    public function openSlot(
        User $buyer,
        string $geo,
        int $volume,
        int $priceCents,
        PricingModel $pricingModel,
        DateTimeInterface $deadline,
    ): Slot {
        $slot = $this->board->openSlot(
            $this->organization($buyer),
            $buyer,
            $geo,
            $volume,
            $priceCents,
            $pricingModel,
            $deadline,
        );
        $this->entityManager->persist($slot);
        $this->entityManager->run();

        return $slot;
    }

    public function openDemand(
        User $partner,
        string $geo,
        int $volume,
        int $priceCents,
        PricingModel $pricingModel,
        DateTimeInterface $serviceOn,
        string $opensAt,
        string $closesAt,
    ): Demand {
        $demand = $this->board->openDemand(
            $this->organization($partner),
            $partner,
            $geo,
            $volume,
            $priceCents,
            $pricingModel,
            $serviceOn,
            $opensAt,
            $closesAt,
        );
        $this->entityManager->persist($demand);
        $this->entityManager->run();

        return $demand;
    }

    public function acceptSlot(User $partner, string $id): Acceptance
    {
        $slot = $this->slots->findById($id);
        if ($slot === null || $slot->getOrganizationId() !== $partner->getOrganizationId()) {
            throw new ListingMissingException();
        }

        $acceptance = $this->board->takeSlot($slot, $partner);
        $this->entityManager->persist($slot);
        $this->entityManager->persist($acceptance);
        $this->entityManager->run();

        return $acceptance;
    }

    public function acceptDemand(User $buyer, string $id): Acceptance
    {
        $demand = $this->demands->findById($id);
        if ($demand === null || $demand->getOrganizationId() !== $buyer->getOrganizationId()) {
            throw new ListingMissingException();
        }

        $acceptance = $this->board->takeDemand($demand, $buyer);
        $this->entityManager->persist($demand);
        $this->entityManager->persist($acceptance);
        $this->entityManager->run();

        return $acceptance;
    }

    private function organization(User $user): Organization
    {
        $organization = $this->organizations->findById($user->getOrganizationId());
        if ($organization === null) {
            throw new RuntimeException('Organization is missing');
        }

        return $organization;
    }
}
