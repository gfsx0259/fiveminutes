<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Lead\Lead;
use App\Value\AgeBracket;
use Doctrine\Common\Collections\Collection;

final readonly class PresetMatch
{
    public function __construct(private PresetRepository $presets) {}

    public function forLead(Lead $lead): ?Preset
    {
        if (
            $lead->getCountry() === ''
            || $lead->getTrafficSource() === ''
            || $lead->getAge() === ''
        ) {
            return null;
        }

        $candidates = $this->presets->findByOrganization($lead->getOrganizationId());
        foreach ($this->genderAttempts($lead) as $gender) {
            $preset = $this->firstMatching($candidates, $lead, $gender);
            if ($preset instanceof Preset) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function genderAttempts(Lead $lead): array
    {
        if ($lead->getGender() === '') {
            return [Gender::All->value];
        }
        if ($lead->getGender() === Gender::All->value) {
            return [Gender::All->value];
        }

        return [$lead->getGender(), Gender::All->value];
    }

    /**
     * @param Collection<int, Preset> $candidates
     */
    private function firstMatching(Collection $candidates, Lead $lead, string $gender): ?Preset
    {
        foreach ($candidates as $preset) {
            if (!$preset instanceof Preset) {
                continue;
            }
            if ($this->matches($preset, $lead, $gender)) {
                return $preset;
            }
        }

        return null;
    }

    private function matches(Preset $preset, Lead $lead, string $gender): bool
    {
        if ($preset->getCountry() !== $lead->getCountry()) {
            return false;
        }
        if (strcasecmp($preset->getTrafficSource(), $lead->getTrafficSource()) !== 0) {
            return false;
        }
        if (!AgeBracket::fits($preset->getAge(), $lead->getAge())) {
            return false;
        }
        if ($preset->getGender() !== $gender) {
            return false;
        }

        return true;
    }
}