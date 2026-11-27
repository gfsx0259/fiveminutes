<?php

declare(strict_types=1);

namespace App\Context\Tree;

use App\Context\Lead\Lead;
use App\Context\Market\Permit;
use App\Context\Market\Preset;
use App\Context\Market\PresetMatch;
use App\Value\MetaMap;
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
        $token = $lead->metaToken();
        foreach ($this->presets->allForLead($lead) as $preset) {
            $specific = $token === MetaMap::EMPTY ? null : $this->pass($lead, $preset, $token, $now);
            if ($specific !== null) {
                return $specific;
            }
            $open = $this->pass($lead, $preset, MetaMap::EMPTY, $now);
            if ($open !== null) {
                return $open;
            }
        }

        return null;
    }

    private function pass(Lead $lead, Preset $preset, string $meta, DateTimeImmutable $now): ?Pick
    {
        $day = $this->kept($lead, $preset, false, $now, $meta);
        if ($day !== null) {
            return $day;
        }

        return $this->kept($lead, $preset, true, $now, $meta);
    }

    private function kept(Lead $lead, Preset $preset, bool $stock, DateTimeImmutable $now, string $meta): ?Pick
    {
        $pick = $this->matcher->take($lead->getOrganizationId(), $preset->getId(), $lead->getCountry(), $stock, $now, $meta);
        if ($pick === null) {
            return null;
        }
        if ($this->permits->allows($lead, $pick, $preset)) {
            return $pick;
        }
        $this->pool->release($lead->getOrganizationId(), $pick->orderId, $pick->localDay);

        return null;
    }
}
