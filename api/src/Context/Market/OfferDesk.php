<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use App\Context\User\User;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class OfferDesk
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
        private PresetRepository $presets,
        private OfferRepository $offers,
        private EntityManagerInterface $entityManager,
    ) {}

    public function open(
        User $buyer,
        string $presetId,
        string $geo,
        int $volume,
        bool $stock,
        ?DateTimeImmutable $serviceOn,
    ): Offer {
        if ($buyer->getRole() !== UserRole::Buyer) {
            throw new DomainException('Only a buyer can open an offer');
        }
        $preset = $this->presets->findById($presetId);
        if (!$preset instanceof Preset || $preset->getOrganizationId() !== $buyer->getOrganizationId()) {
            throw new PresetMissingException('Preset was not found');
        }
        if ($geo !== $preset->getCountry()) {
            throw new InvalidArgumentException('Geo must be the preset country');
        }

        $offer = new Offer(
            $this->uuids->uuid4()->toString(),
            $buyer->getOrganizationId(),
            $buyer->getId(),
            $preset->getId(),
            $geo,
            $volume,
            $stock,
            $stock ? null : $this->day($serviceOn),
            0,
            OfferStatus::Pending->value,
            $this->clock->now(),
            $this->clock->now(),
        );
        $this->entityManager->persist($offer);
        $this->entityManager->run();

        return $offer;
    }

    public function approve(User $admin, string $id): Offer
    {
        return $this->decide($admin, $id, true);
    }

    public function reject(User $admin, string $id): Offer
    {
        return $this->decide($admin, $id, false);
    }

    private function decide(User $admin, string $id, bool $approve): Offer
    {
        if ($admin->getRole() !== UserRole::Admin) {
            throw new DomainException('Only an admin can moderate an offer');
        }
        $offer = $this->offers->findById($id);
        if (!$offer instanceof Offer || $offer->getOrganizationId() !== $admin->getOrganizationId()) {
            throw new OfferMissingException('Offer was not found');
        }
        if ($approve) {
            $offer->approve($this->clock->now());
        } else {
            $offer->reject($this->clock->now());
        }
        $this->entityManager->persist($offer);
        $this->entityManager->run();

        return $offer;
    }

    private function day(?DateTimeImmutable $serviceOn): DateTimeImmutable
    {
        if (!$serviceOn instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Date must look like 2026-11-16');
        }
        $day = $serviceOn->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        if ($day !== $now->format('Y-m-d') && $day !== $now->modify('+1 day')->format('Y-m-d')) {
            throw new InvalidArgumentException('Offer date must be today or tomorrow');
        }

        return $serviceOn;
    }
}
