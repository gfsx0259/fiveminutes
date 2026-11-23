<?php

declare(strict_types=1);

namespace App\Context\Market;

final readonly class ShelfAgePolicyQuery
{
    private const string AGE_MINUTES = 'dateDiff(\'minute\', created_at, now())';

    public function __construct(private ShelfAgePolicy $policy) {}

    public function ageMinutesExpression(): string
    {
        return self::AGE_MINUTES;
    }

    public function sellableAgePredicate(): string
    {
        $liveEnd = $this->policy->liveZone()->endMinutes ?? 0;

        return self::AGE_MINUTES . ' >= ' . $liveEnd;
    }

    public function pickOrderSql(?StockPickPreference $pick = null): string
    {
        $pick ??= StockPickPreference::default();
        $age = self::AGE_MINUTES;
        $newest = $pick->priority === StockPickPriority::NewestFirst;
        $parts = [];
        foreach ($this->policy->zones as $zone) {
            if (!$zone->sellableOnShelf) {
                continue;
            }
            $rank = match ($zone->id) {
                ShelfAgeZone::EXPIRED => $newest ? 2 : 0,
                ShelfAgeZone::GOOD => 1,
                ShelfAgeZone::FRESH => $newest ? 0 : 2,
                default => 9,
            };
            if ($zone->endMinutes === null) {
                $parts[] = sprintf('%s >= %d, %d', $age, $zone->startMinutes, $rank);
            } else {
                $parts[] = sprintf(
                    '%s >= %d AND %s < %d, %d',
                    $age,
                    $zone->startMinutes,
                    $age,
                    $zone->endMinutes,
                    $rank,
                );
            }
        }

        $createdAt = $newest ? 'created_at DESC' : 'created_at ASC';

        return 'multiIf(' . implode(', ', $parts) . ', 9), ' . $createdAt;
    }

    public function zoneAgePredicate(ShelfAgeZone $zone): string
    {
        $age = self::AGE_MINUTES;
        if ($zone->endMinutes === null) {
            return sprintf('%s >= %d', $age, $zone->startMinutes);
        }

        return sprintf(
            '%s >= %d AND %s < %d',
            $age,
            $zone->startMinutes,
            $age,
            $zone->endMinutes,
        );
    }

    public function countIfForZone(ShelfAgeZone $zone): string
    {
        $age = self::AGE_MINUTES;
        if ($zone->endMinutes === null) {
            return sprintf('countIf(%s >= %d)', $age, $zone->startMinutes);
        }

        return sprintf(
            'countIf(%s >= %d AND %s < %d)',
            $age,
            $zone->startMinutes,
            $age,
            $zone->endMinutes,
        );
    }
}