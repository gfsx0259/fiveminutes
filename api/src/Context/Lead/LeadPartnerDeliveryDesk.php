<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\User\User;
use App\Context\User\UserRole;
use DomainException;

final readonly class LeadPartnerDeliveryDesk
{
    private const array CLIENT_STATUSES = [
        'new',
        'lowpotential',
        'callback',
        'highpotencial',
        'no_answer',
        'wrong_number',
    ];

    public function __construct(
        private LeadRepository $leads,
        private Clock $clock,
    ) {}

    public function mockDeliver(User $actor, string $uuid): Lead
    {
        $lead = $this->ownedLead($actor, $uuid);
        $this->requireDeliverable($lead);

        $failed = random_int(1, 100) <= 15;
        if ($failed) {
            $updated = $lead->withPartnerDelivery(false, true, '', false, $this->clock);
        } else {
            $updated = $lead->withPartnerDelivery(
                true,
                false,
                self::CLIENT_STATUSES[array_rand(self::CLIENT_STATUSES)],
                random_int(0, 1) === 1,
                $this->clock,
            );
        }

        $this->leads->add($updated);

        return $updated;
    }

    private function ownedLead(User $actor, string $uuid): Lead
    {
        $lead = $this->leads->findByUuid($uuid);
        if ($lead === null || $lead->getOrganizationId() !== $actor->getOrganizationId()) {
            throw new DomainException('Lead was not found');
        }
        if ($actor->getRole() === UserRole::Admin) {
            return $lead;
        }
        if ($actor->getRole() === UserRole::Partner && $lead->getSellerId() === $actor->getId()) {
            return $lead;
        }

        throw new DomainException('You cannot change this lead');
    }

    private function requireDeliverable(Lead $lead): void
    {
        if ($lead->getSellerId() === null) {
            throw new DomainException('Lead was not sold to a partner');
        }
        if ($lead->isDelivered() || $lead->hasDeliveryError()) {
            throw new DomainException('Lead delivery was already recorded');
        }
    }
}