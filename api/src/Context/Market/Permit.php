<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use App\Context\Lead\Lead;
use App\Context\Lead\LeadRejectedException;
use App\Context\Tree\Pick;
use Cycle\ORM\EntityManagerInterface;
use DateTimeZone;

final readonly class Permit
{
    public function __construct(
        private PresetMatch $presets,
        private OfferRepository $offers,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    public function allows(Lead $lead, Pick $pick, ?Preset $preset = null): bool
    {
        $preset ??= $this->presetOf($lead);

        return $preset instanceof Preset && $this->covering($lead, $preset, $pick) !== null;
    }

    public function assertRecognized(Lead $lead): void
    {
        if (!$this->presets->forLead($lead) instanceof Preset) {
            throw new LeadRejectedException(['preset' => 'No preset matches this lead']);
        }
    }

    public function consume(Lead $lead, ?Pick $pick): void
    {
        $preset = $this->presetOf($lead);
        if (!$preset instanceof Preset) {
            return;
        }
        $offer = $this->covering($lead, $preset, $pick);
        if ($offer === null || !$offer->trySupply($this->clock->now())) {
            return;
        }
        $this->entityManager->persist($offer);
    }

    private function presetOf(Lead $lead): ?Preset
    {
        $wanted = $lead->getPresetId();
        foreach ($this->presets->allForLead($lead) as $preset) {
            if ($wanted === null || $preset->getId() === $wanted) {
                return $preset;
            }
        }

        return $this->presets->forLead($lead);
    }

    private function covering(Lead $lead, Preset $preset, ?Pick $pick): ?Offer
    {
        $today = $this->today();
        $wanted = $pick === null ? [false, true] : [$pick->stock];
        foreach ($wanted as $stock) {
            foreach ($this->offers->findByBuyer($lead->getOrganizationId(), $lead->getBuyerId()) as $offer) {
                if ($offer->hasRoom() && $offer->covers($preset->getId(), $lead->getCountry(), $stock, $today)) {
                    return $offer;
                }
            }
        }

        return null;
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    }
}
