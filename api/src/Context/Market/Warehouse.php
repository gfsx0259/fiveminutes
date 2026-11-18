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
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function inventory(User $user): array
    {
        if ($user->getRole() !== UserRole::Partner && $user->getRole() !== UserRole::Admin) {
            throw new DomainException('Only a partner can browse stock');
        }

        $organizationId = $user->getOrganizationId();
        $rows = [];
        foreach ($this->presets->findByOrganization($organizationId) as $preset) {
            if (!$preset instanceof Preset) {
                continue;
            }
            $buckets = $this->leads->countInStockByLeadGender($organizationId, $preset);
            foreach ($buckets as $leadGender => $available) {
                if ($available < 1) {
                    continue;
                }
                $rows[] = [
                    'presetId' => $preset->getId(),
                    'country' => $preset->getCountry(),
                    'trafficSource' => $preset->getTrafficSource(),
                    'age' => $preset->getAge(),
                    'gender' => $leadGender,
                    'cr' => $preset->getCr(),
                    'cplPriceCents' => $preset->getCplPriceCents(),
                    'cpaPriceCents' => $preset->getCpaPriceCents(),
                    'available' => $available,
                ];
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $key = static fn (array $row): string => implode('|', [
                $row['country'],
                $row['trafficSource'],
                $row['gender'],
                $row['age'],
                $row['presetId'],
            ]);

            return $key($left) <=> $key($right);
        });

        return $rows;
    }

    public function available(User $user, string $presetId, ?string $leadGender = null): int
    {
        $preset = $this->preset($user, $presetId);

        return $this->leads->countInStockForPreset($user->getOrganizationId(), $preset, $leadGender);
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