<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\Lead\Lead;

final readonly class StockPickQuote
{
    /**
     * @param list<array{
     *     zoneId: string,
     *     label: string,
     *     count: int,
     *     unitPriceCents: int,
     *     discountPercent: int,
     *     lineTotalCents: int
     * }> $lines
     * @param list<array{lead: Lead, zoneId: string, effectivePriceCents: int}> $picks
     */
    public function __construct(
        public int $basePriceCents,
        public array $lines,
        public int $totalCents,
        public array $picks,
    ) {}
}