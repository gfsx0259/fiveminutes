<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Lead\LeadRepository;
use App\Context\User\User;
use App\Context\User\UserRole;
use DomainException;

final readonly class Warehouse
{
    public function __construct(
        private PresetRepository $presets,
        private LeadRepository $leads,
        private OrderRepository $orders,
    ) {}

    /**
     * Partner catalog: one row per preset with stock qty and today's day-order cap fill.
     *
     * @return list<array<string, mixed>>
     */
    public function inventory(User $user): array
    {
        if ($user->getRole() !== UserRole::Partner && $user->getRole() !== UserRole::Admin) {
            throw new DomainException('Only a partner can browse stock');
        }

        $organizationId = $user->getOrganizationId();
        $daily = $this->dailyCapsByPreset($user);
        $rows = [];
        foreach ($this->presets->findByOrganization($organizationId) as $preset) {
            if (!$preset instanceof Preset) {
                continue;
            }
            $available = $this->leads->countInStockForPreset($organizationId, $preset);
            $latest = $this->leads->latestStockAtForPreset($organizationId, $preset);
            $cap = $daily[$preset->getId()] ?? ['volume' => 0, 'supplied' => 0];
            $rows[] = [
                'presetId' => $preset->getId(),
                'vertical' => $preset->getVertical(),
                'country' => $preset->getCountry(),
                'trafficSource' => $preset->getTrafficSource(),
                'paymentModel' => $preset->getPaymentModel(),
                'conversion' => $preset->getCr(),
                'stockPriceCents' => $preset->getStockPriceCents(),
                'floorPriceCents' => $preset->getFloorPriceCents(),
                'available' => $available,
                'dailyCapVolume' => $cap['volume'],
                'dailyCapSupplied' => $cap['supplied'],
                'updatedAt' => $latest?->format(DATE_ATOM),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $a = $left['updatedAt'] ?? '';
            $b = $right['updatedAt'] ?? '';
            if ($a === $b) {
                return ($left['presetId'] ?? '') <=> ($right['presetId'] ?? '');
            }
            if ($a === '') {
                return 1;
            }
            if ($b === '') {
                return -1;
            }

            return $b <=> $a;
        });

        return $rows;
    }

    public function available(User $user, string $presetId, ?string $leadGender = null): int
    {
        $preset = $this->preset($user, $presetId);

        return $this->leads->countInStockForPreset($user->getOrganizationId(), $preset, $leadGender);
    }

    /**
     * @return array<string, array{volume: int, supplied: int}>
     */
    private function dailyCapsByPreset(User $user): array
    {
        $today = gmdate('Y-m-d');
        $organizationId = $user->getOrganizationId();
        $source = $user->getRole() === UserRole::Partner
            ? $this->orders->findByPartner($organizationId, $user->getId())
            : $this->orders->findByOrganization($organizationId);

        $grouped = [];
        foreach ($source as $order) {
            if ($order->isStock() || $order->getStatus() !== OrderStatus::InProgress) {
                continue;
            }
            $serviceOn = $order->getServiceOn();
            if ($serviceOn?->format('Y-m-d') !== $today) {
                continue;
            }
            $presetId = $order->getPresetId();
            if (!isset($grouped[$presetId])) {
                $grouped[$presetId] = ['volume' => 0, 'supplied' => 0];
            }
            $grouped[$presetId]['volume'] += $order->getVolume();
            $grouped[$presetId]['supplied'] += $order->getSuppliedCount();
        }

        return $grouped;
    }

    private function preset(User $user, string $presetId): Preset
    {
        $preset = $this->presets->findById($presetId);
        if (!$preset instanceof Preset || $preset->getOrganizationId() !== $user->getOrganizationId()) {
            throw new PresetMissingException('Preset was not found');
        }

        return $preset;
    }
}