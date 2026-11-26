<?php

declare(strict_types=1);

namespace App\Context\Team;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

enum BudgetPeriod: string
{
    case Total = 'total';
    case Month = 'month';
    case Week = 'week';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Total',
            self::Month => 'Monthly',
            self::Week => 'Weekly',
        };
    }

    public function startsAt(DateTimeInterface $now): ?DateTimeImmutable
    {
        $utc = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));

        return match ($this) {
            self::Total => null,
            self::Month => $utc->modify('first day of this month')->setTime(0, 0),
            self::Week => $utc->modify('monday this week')->setTime(0, 0),
        };
    }

    public function resetsAt(DateTimeInterface $now): ?DateTimeImmutable
    {
        $start = $this->startsAt($now);

        return match ($this) {
            self::Total => null,
            self::Month => $start?->modify('+1 month'),
            self::Week => $start?->modify('+1 week'),
        };
    }
}
