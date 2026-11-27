<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Lead\Lead;
use App\Value\AgeBracket;
use ValueError;

final readonly class PresetMatch
{
    public function __construct(private PresetRepository $presets) {}

    public static function star(string $value): bool
    {
        $value = strtolower(trim($value));

        return $value === '' || $value === '*' || $value === 'all' || $value === 'any';
    }

    public function forLead(Lead $lead): ?Preset
    {
        return $this->allForLead($lead)[0] ?? null;
    }

    /**
     * Every preset the lead fits. The same branch can exist twice, once per payment model,
     * and only one of those pools may have an order.
     *
     * @return list<Preset>
     */
    public function allForLead(Lead $lead): array
    {
        if (
            $lead->getCountry() === ''
            || $lead->getTrafficSource() === ''
            || $this->vertical($lead) === ''
        ) {
            return [];
        }

        $matched = [];
        foreach ($this->presets->findByOrganization($lead->getOrganizationId()) as $preset) {
            if ($preset instanceof Preset && $this->matches($preset, $lead)) {
                $matched[] = $preset;
            }
        }
        usort($matched, static fn (Preset $left, Preset $right): int => self::paymentOrder($left->getPaymentModel()) <=> self::paymentOrder($right->getPaymentModel()));

        return $matched;
    }

    /** The lead has no payment model, so folders are tried in this fixed order. */
    public static function paymentOrder(string $model): int
    {
        return match (strtolower(trim($model))) {
            'cpl' => 0,
            'cpa' => 1,
            default => 2,
        };
    }

    private function matches(Preset $preset, Lead $lead): bool
    {
        if (strcasecmp($preset->getVertical(), $this->vertical($lead)) !== 0) {
            return false;
        }
        if ($preset->getCountry() !== $lead->getCountry()) {
            return false;
        }
        if (strcasecmp($preset->getTrafficSource(), $lead->getTrafficSource()) !== 0) {
            return false;
        }
        if (!$this->ageMatches($preset->getAge(), $lead->getAge())) {
            return false;
        }
        if (!$this->genderMatches($preset->getGender(), $lead->getGender())) {
            return false;
        }

        return true;
    }

    private function ageMatches(string $presetAge, string $leadAge): bool
    {
        if (self::star($presetAge)) {
            return $leadAge === '';
        }
        if ($leadAge === '') {
            return false;
        }

        return AgeBracket::fits($presetAge, $leadAge);
    }

    private function genderMatches(string $presetGender, string $leadGender): bool
    {
        if (self::star($presetGender)) {
            return $leadGender === '';
        }

        return $presetGender === $leadGender;
    }

    private function vertical(Lead $lead): string
    {
        try {
            return Vertical::from(strtolower(trim($lead->getVertical())))->value;
        } catch (ValueError) {
            return '';
        }
    }
}
