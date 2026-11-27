<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\User\NotBuyerException;
use App\Context\User\User;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;

/**
 * Settles the buyer's setup: the first test lead that came through proves the integration works.
 */
final readonly class IntegrationCheck
{
    public function __construct(
        private LeadRepository $leads,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    /**
     * The newest test lead since the given moment, or null while none has arrived. Finding one settles the
     * setup, so the cabinet can keep the guide folded on the next visit.
     */
    public function verify(User $buyer, ?DateTimeImmutable $since): ?Lead
    {
        if ($buyer->getRole() !== UserRole::Buyer) {
            throw new NotBuyerException();
        }

        $lead = $this->leads->findLatestTest($buyer->getOrganizationId(), $buyer->getId(), $since);
        if ($lead !== null) {
            $buyer->completeIntegration($this->clock);
            $this->entityManager->persist($buyer);
            $this->entityManager->run();
        }

        return $lead;
    }
}
