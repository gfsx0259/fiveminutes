<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use App\Context\Lead\Lead;
use App\Context\Lead\LeadRepository;
use InvalidArgumentException;

final readonly class StockPickPlan
{
    public function __construct(
        private LeadRepository $leads,
        private ShelfAgePolicyResolver $resolver,
        private Clock $clock,
    ) {}

    public function quote(
        string $organizationId,
        Preset $preset,
        int $quantity,
        ShelfAgePolicy $policy,
        ?string $leadGender = null,
    ): StockPickQuote {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        $now = $this->clock->now();
        $picked = $this->leads->findSellableStockForPreset(
            $organizationId,
            $preset,
            $quantity,
            $policy,
            $leadGender,
        );
        if (count($picked) < $quantity) {
            throw new InvalidArgumentException('Not enough leads in stock');
        }

        $base = $preset->getBasePriceCents();
        /** @var list<array{lead: Lead, zoneId: string, effectivePriceCents: int}> $pickRows */
        $pickRows = [];
        /** @var array<string, array{zoneId: string, label: string, count: int, discountPercent: int, unitPriceCents: int, lineTotalCents: int}> $buckets */
        $buckets = [];
        $total = 0;

        foreach ($picked as $lead) {
            $zone = $this->resolver->zoneForLead($policy, $lead->getCreatedAt(), $now);
            $unit = $this->resolver->effectivePriceCents($base, $zone);
            $total += $unit;
            $pickRows[] = [
                'lead' => $lead,
                'zoneId' => $zone->id,
                'effectivePriceCents' => $unit,
            ];
            if (!isset($buckets[$zone->id])) {
                $buckets[$zone->id] = [
                    'zoneId' => $zone->id,
                    'label' => $zone->label,
                    'count' => 0,
                    'discountPercent' => $zone->discountPercent,
                    'unitPriceCents' => $unit,
                    'lineTotalCents' => 0,
                ];
            }
            $buckets[$zone->id]['count']++;
            $buckets[$zone->id]['lineTotalCents'] += $unit;
        }

        $lines = array_values($buckets);

        return new StockPickQuote($base, $lines, $total, $pickRows);
    }
}