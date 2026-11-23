<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Lead\LeadRepository;
use App\Context\Lead\LiveDayInjectStore;
use App\Context\User\User;
use App\Context\User\UserRole;
use DomainException;
use InvalidArgumentException;

final readonly class Warehouse
{
    public function __construct(
        private PresetRepository $presets,
        private LeadRepository $leads,
        private OrderRepository $orders,
        private OrganizationShelfAge $shelfAge,
        private StockPickPlan $pickPlan,
        private LiveDayInjectStore $liveInject,
    ) {}

    /**
     * @return array{shelfAgePolicy: array<string, mixed>, stock: list<array<string, mixed>>}
     */
    public function inventory(User $user): array
    {
        if ($user->getRole() !== UserRole::Partner && $user->getRole() !== UserRole::Admin) {
            throw new DomainException('Only a partner can browse stock');
        }

        $organizationId = $user->getOrganizationId();
        $policy = $this->shelfAge->forOrganization($organizationId);
        $daily = $this->dailyCapsByPreset($user);
        $rows = [];
        foreach ($this->presets->findByOrganization($organizationId) as $preset) {
            if (!$preset instanceof Preset) {
                continue;
            }
            $reservedLive = $this->liveInject->scheduledLeadUuids($organizationId);
            $available = $this->leads->countSellableInStockForPreset($organizationId, $preset, $policy);
            $latest = $available > 0
                ? $this->leads->latestStockAtForPreset($organizationId, $preset)
                : null;
            $freshness = $available > 0
                ? $this->leads->stockShelfMixForPreset($organizationId, $preset, $policy)
                : ['fresh' => 0, 'good' => 0, 'expired' => 0];
            $cap = $daily[$preset->getId()] ?? ['volume' => 0, 'supplied' => 0];
            $rows[] = [
                'presetId' => $preset->getId(),
                'vertical' => $preset->getVertical(),
                'country' => $preset->getCountry(),
                'trafficSource' => $preset->getTrafficSource(),
                'paymentModel' => $preset->getPaymentModel(),
                'conversion' => $preset->getCr(),
                'basePriceCents' => $preset->getBasePriceCents(),
                'available' => $available,
                'liveInStock' => $this->leads->countLiveInStockForPreset(
                    $organizationId,
                    $preset,
                    $policy,
                    excludeLeadUuids: $reservedLive,
                ),
                'stockFreshness' => $freshness,
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

        return [
            'shelfAgePolicy' => $policy->toArray(),
            'stock' => $rows,
        ];
    }

    public function available(User $user, string $presetId, ?string $leadGender = null): int
    {
        $preset = $this->preset($user, $presetId);
        $policy = $this->shelfAge->forOrganization($user->getOrganizationId());

        return $this->leads->countSellableInStockForPreset(
            $user->getOrganizationId(),
            $preset,
            $policy,
            $leadGender,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(
        User $user,
        string $presetId,
        int $quantity,
        ?string $leadGender = null,
        ?StockPickPreference $pick = null,
    ): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }
        $preset = $this->preset($user, $presetId);
        $policy = $this->shelfAge->forOrganization($user->getOrganizationId());
        $quote = $this->pickPlan->quote(
            $user->getOrganizationId(),
            $preset,
            $quantity,
            $policy,
            $leadGender,
            $pick,
        );

        return [
            'presetId' => $presetId,
            'quantity' => $quantity,
            'basePriceCents' => $quote->basePriceCents,
            'lines' => $quote->lines,
            'totalCents' => $quote->totalCents,
            ...($pick ?? StockPickPreference::default())->toArray(),
        ];
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