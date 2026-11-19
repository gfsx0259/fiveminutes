<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use App\Context\User\User;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;
use DomainException;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class PresetDesk
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
        private PresetRepository $presets,
        private OrderRepository $orders,
        private OfferRepository $offers,
        private EntityManagerInterface $entityManager,
    ) {}

    public function open(
        User $admin,
        string $country,
        string $trafficSource,
        string $age,
        string $gender,
        string $vertical,
        string $paymentModel,
        int $cr,
        int $floorPriceCents,
        int $stockPriceCents,
    ): Preset {
        if ($admin->getRole() !== UserRole::Admin) {
            throw new DomainException('Only an admin can open a preset');
        }

        $preset = new Preset(
            $this->uuids->uuid4()->toString(),
            $admin->getOrganizationId(),
            $admin->getId(),
            $country,
            $trafficSource,
            $age,
            $gender,
            $vertical,
            $paymentModel,
            $cr,
            $floorPriceCents,
            $stockPriceCents,
            $this->clock->now(),
        );
        $this->entityManager->persist($preset);
        $this->entityManager->run();

        return $preset;
    }

    public function remove(User $admin, string $id): void
    {
        if ($admin->getRole() !== UserRole::Admin) {
            throw new DomainException('Only an admin can remove a preset');
        }

        $preset = $this->presets->findById($id);
        if (!$preset instanceof Preset || $preset->getOrganizationId() !== $admin->getOrganizationId()) {
            throw new PresetMissingException('Preset not found');
        }

        if ($this->orders->select()->where(['presetId' => $id])->limit(1)->fetchOne() !== null) {
            throw new DomainException('Preset has orders and cannot be removed');
        }
        if ($this->offers->select()->where(['presetId' => $id])->limit(1)->fetchOne() !== null) {
            throw new DomainException('Preset has offers and cannot be removed');
        }

        $this->entityManager->delete($preset);
        $this->entityManager->run();
    }
}