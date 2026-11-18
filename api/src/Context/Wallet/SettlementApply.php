<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Clock;
use App\Context\Lead\Lead;
use App\Context\Lead\LeadRepository;
use App\Context\Lead\LeadStatus;
use DomainException;

final readonly class SettlementApply
{
    public function __construct(
        private SettlementDesk $desk,
        private LeadRepository $leads,
        private Clock $clock,
    ) {}

    public function apply(SettlementPayload $payload): void
    {
        $lead = $this->leads->findByUuid($payload->leadUuid);
        if ($lead === null) {
            throw new DomainException('Lead was not found for settlement');
        }
        $this->assertMatches($lead->getUuid(), $payload, $lead->getOrganizationId(), $lead->getBuyerId(), $lead->getSellerId(), $lead->getOrderId(), $lead->getPriceCents());

        match ($payload->kind) {
            SettlementEvent::CplDelivered => $this->applyCpl($lead),
            SettlementEvent::CpaSold => $this->desk->settleCpaSold($lead),
        };
    }

    private function applyCpl(Lead $lead): void
    {
        $this->desk->settleCplDelivered($lead);
        $this->leads->add($lead->withStatus(LeadStatus::Delivered, $this->clock));
    }

    private function assertMatches(
        string $leadUuid,
        SettlementPayload $payload,
        string $organizationId,
        string $buyerId,
        ?string $sellerId,
        ?string $orderId,
        int $priceCents,
    ): void {
        if ($leadUuid !== $payload->leadUuid) {
            throw new DomainException('Lead uuid mismatch');
        }
        if ($organizationId !== $payload->organizationId
            || $buyerId !== $payload->buyerId
            || $sellerId !== $payload->partnerId
            || $orderId !== $payload->orderId
            || $priceCents !== $payload->catalogCents) {
            throw new DomainException('Settlement payload does not match stored lead');
        }
    }
}