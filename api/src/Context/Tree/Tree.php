<?php

declare(strict_types=1);

namespace App\Context\Tree;

use App\Context\Lead\Lead;
use App\Context\Market\Permit;
use App\Context\Market\Preset;
use App\Context\Market\PresetMatch;
use DateTimeImmutable;

final readonly class Tree
{
    public function __construct(
        private PresetMatch $presets,
        private DeficitMatch $matcher,
        private Permit $permits,
        private OrderPool $pool,
    ) {}

    public function take(Lead $lead, DateTimeImmutable $now): ?Pick
    {
        $preset = $this->presets->forLead($lead);
        if (!$preset instanceof Preset) {
            return null;
        }

        $day = $this->kept($lead, $preset->getId(), false, $now);
        if ($day !== null) {
            return $day;
        }

        return $this->kept($lead, $preset->getId(), true, $now);
    }

    private function kept(Lead $lead, string $presetId, bool $stock, DateTimeImmutable $now): ?Pick
    {
        $pick = $this->matcher->take($lead->getOrganizationId(), $presetId, $lead->getCountry(), $stock, $now);
        if ($pick === null) {
            return null;
        }
        if ($this->permits->allows($lead, $pick)) {
            return $pick;
        }
        $this->pool->release($lead->getOrganizationId(), $pick->orderId, $pick->localDay);

        return null;
    }
}
