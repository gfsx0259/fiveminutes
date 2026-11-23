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

    public function pickOrderSql(): string
    {
        $age = self::AGE_MINUTES;
        $parts = [];
        foreach ($this->policy->zones as $zone) {
            if (!$zone->sellableOnShelf) {
                continue;
            }
            $rank = match ($zone->id) {
                ShelfAgeZone::EXPIRED => 0,
                ShelfAgeZone::GOOD => 1,
                ShelfAgeZone::FRESH => 2,
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

        return 'multiIf(' . implode(', ', $parts) . ', 9), created_at ASC';
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