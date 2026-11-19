<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use DomainException;

final readonly class Cabinet
{
    public function __construct(
        private PresetRepository $presets,
        private OrderRepository $orders,
        private OfferRepository $offers,
        private UserRepository $users,
        private OrganizationMarkup $markup,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function presets(User $user): array
    {
        $rows = [];
        foreach ($this->presets->findByOrganization($user->getOrganizationId()) as $preset) {
            $rows[] = [
                'id' => $preset->getId(),
                'country' => $preset->getCountry(),
                'trafficSource' => $preset->getTrafficSource(),
                'age' => $preset->getAge(),
                'gender' => $preset->getGender(),
                'vertical' => $preset->getVertical(),
                'paymentModel' => $preset->getPaymentModel(),
                'cr' => $preset->getCr(),
                'floorPriceCents' => $preset->getFloorPriceCents(),
                'stockPriceCents' => $preset->getStockPriceCents(),
                'createdAt' => $preset->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function orders(User $user): array
    {
        if ($user->getRole() === UserRole::Buyer) {
            throw new DomainException('A buyer sees caps, not orders');
        }
        $source = $user->getRole() === UserRole::Partner
            ? $this->orders->findByPartner($user->getOrganizationId(), $user->getId())
            : $this->orders->findByOrganization($user->getOrganizationId());
        $presets = $this->presetIndex($user->getOrganizationId());
        $emails = $this->emails($user->getOrganizationId());
        $pricing = $this->markup->forOrganization($user->getOrganizationId());
        $rows = [];
        foreach ($source as $order) {
            $preset = $presets[$order->getPresetId()] ?? null;
            $serviceOn = $order->getServiceOn();
            $catalogCents = $order->getPriceCents();
            $rows[] = [
                'id' => $order->getId(),
                'presetId' => $order->getPresetId(),
                'trafficSource' => $preset?->getTrafficSource() ?? '',
                'age' => $preset?->getAge() ?? '',
                'gender' => $preset?->getGender() ?? '',
                'geo' => $order->getGeo(),
                'volume' => $order->getVolume(),
                'supplied' => $order->getSuppliedCount(),
                'priceCents' => $catalogCents,
                'paymentModel' => $order->getPaymentModel(),
                'partnerPriceCents' => $pricing->partnerPriceCents($catalogCents),
                'stock' => $order->isStock(),
                'serviceOn' => $serviceOn?->format('Y-m-d'),
                'opensAt' => $order->getOpensAt(),
                'closesAt' => $order->getClosesAt(),
                'status' => $order->getStatus()->value,
                'partnerId' => $order->getPartnerId(),
                'partnerEmail' => $emails[$order->getPartnerId()] ?? '',
                'createdAt' => $order->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function caps(User $user): array
    {
        if ($user->getRole() === UserRole::Partner) {
            throw new DomainException('A partner sees orders, not caps');
        }
        $presets = $this->presetIndex($user->getOrganizationId());
        $grouped = [];
        foreach ($this->orders->findByOrganization($user->getOrganizationId()) as $order) {
            if ($order->getStatus() !== OrderStatus::InProgress) {
                continue;
            }
            $serviceOn = $order->getServiceOn();
            $day = $order->isStock() ? 'stock' : ($serviceOn?->format('Y-m-d') ?? '');
            $key = $order->getPresetId() . '|' . $order->getPaymentModel() . '|' . $order->getGeo() . '|' . $day;
            if (!isset($grouped[$key])) {
                $preset = $presets[$order->getPresetId()] ?? null;
                $grouped[$key] = [
                    'presetId' => $order->getPresetId(),
                    'trafficSource' => $preset?->getTrafficSource() ?? '',
                    'age' => $preset?->getAge() ?? '',
                    'gender' => $preset?->getGender() ?? '',
                    'paymentModel' => $order->getPaymentModel(),
                    'cr' => $preset?->getCr() ?? 0,
                    'geo' => $order->getGeo(),
                    'stock' => $order->isStock(),
                    'serviceOn' => $order->isStock() ? null : $day,
                    'volume' => 0,
                    'supplied' => 0,
                ];
            }
            $grouped[$key]['volume'] += $order->getVolume();
            $grouped[$key]['supplied'] += $order->getSuppliedCount();
        }

        return array_values($grouped);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offers(User $user): array
    {
        if ($user->getRole() === UserRole::Partner) {
            throw new DomainException('A partner does not see offers');
        }
        $source = $user->getRole() === UserRole::Buyer
            ? $this->offers->findByBuyer($user->getOrganizationId(), $user->getId())
            : $this->offers->findByOrganization($user->getOrganizationId());
        $presets = $this->presetIndex($user->getOrganizationId());
        $emails = $this->emails($user->getOrganizationId());
        $rows = [];
        foreach ($source as $offer) {
            $preset = $presets[$offer->getPresetId()] ?? null;
            $serviceOn = $offer->getServiceOn();
            $rows[] = [
                'id' => $offer->getId(),
                'presetId' => $offer->getPresetId(),
                'trafficSource' => $preset?->getTrafficSource() ?? '',
                'age' => $preset?->getAge() ?? '',
                'gender' => $preset?->getGender() ?? '',
                'geo' => $offer->getGeo(),
                'volume' => $offer->getVolume(),
                'supplied' => $offer->getSuppliedCount(),
                'stock' => $offer->isStock(),
                'serviceOn' => $serviceOn?->format('Y-m-d'),
                'status' => $offer->getStatus()->value,
                'buyerId' => $offer->getBuyerId(),
                'buyerEmail' => $emails[$offer->getBuyerId()] ?? '',
                'createdAt' => $offer->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, Preset>
     */
    private function presetIndex(string $organizationId): array
    {
        $index = [];
        foreach ($this->presets->findByOrganization($organizationId) as $preset) {
            $index[$preset->getId()] = $preset;
        }

        return $index;
    }

    /**
     * @return array<string, string>
     */
    private function emails(string $organizationId): array
    {
        $emails = [];
        foreach ($this->users->findByOrganization($organizationId) as $member) {
            $emails[$member->getId()] = $member->getEmail();
        }

        return $emails;
    }
}