<?php

declare(strict_types=1);

namespace App\Context\Market;

use DateTimeInterface;

final readonly class ShelfAgePolicyResolver
{
    public function ageMinutes(DateTimeInterface $createdAt, DateTimeInterface $now): int
    {
        $seconds = $now->getTimestamp() - $createdAt->getTimestamp();

        return max(0, (int) floor($seconds / 60));
    }

    public function zoneForAgeMinutes(ShelfAgePolicy $policy, int $ageMinutes): ShelfAgeZone
    {
        foreach ($policy->zones as $zone) {
            $end = $zone->endMinutes;
            if ($end === null) {
                if ($ageMinutes >= $zone->startMinutes) {
                    return $zone;
                }
                continue;
            }
            if ($ageMinutes >= $zone->startMinutes && $ageMinutes < $end) {
                return $zone;
            }
        }

        return $policy->zoneById(ShelfAgeZone::EXPIRED) ?? $policy->zones[3];
    }

    public function zoneForLead(ShelfAgePolicy $policy, DateTimeInterface $createdAt, DateTimeInterface $now): ShelfAgeZone
    {
        return $this->zoneForAgeMinutes($policy, $this->ageMinutes($createdAt, $now));
    }

    public function effectivePriceCents(int $basePriceCents, ShelfAgeZone $zone): int
    {
        if ($basePriceCents < 1) {
            return 0;
        }
        $discount = max(0, min(100, $zone->discountPercent));
        $multiplier = 100 - $discount;

        return (int) round($basePriceCents * $multiplier / 100);
    }

    /** Pick rank: expired first (0), good (1), fresh (2). */
    public function pickRank(ShelfAgeZone $zone): int
    {
        return match ($zone->id) {
            ShelfAgeZone::EXPIRED => 0,
            ShelfAgeZone::GOOD => 1,
            ShelfAgeZone::FRESH => 2,
            default => 99,
        };
    }
}