<?php

declare(strict_types=1);

namespace App\Context\Market;

use InvalidArgumentException;

final readonly class Markup
{
    public function __construct(private int $percent = 10)
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Percent must be between 0 and 100');
        }
    }

    public function getPercent(): int
    {
        return $this->percent;
    }

    /** Catalog price — what the partner pays. */
    public function partnerPriceCents(int $catalogCents): int
    {
        return max(0, $catalogCents);
    }

    /** Platform fee withheld from the buyer on a sold lead. */
    public function platformFeeCents(int $catalogCents): int
    {
        if ($catalogCents <= 0 || $this->percent === 0) {
            return 0;
        }

        return intdiv($catalogCents * $this->percent + 50, 100);
    }

    /** What the buyer receives after the platform fee. */
    public function buyerNetCents(int $catalogCents): int
    {
        if ($catalogCents <= 0) {
            return 0;
        }

        return $catalogCents - $this->platformFeeCents($catalogCents);
    }
}