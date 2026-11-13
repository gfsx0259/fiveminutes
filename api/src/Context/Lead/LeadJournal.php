<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;

final readonly class LeadJournal
{
    public function __construct(
        private LeadRepository $leads,
        private UserRepository $users,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $organizationId = $user->getOrganizationId();
        $leads = match ($user->getRole()) {
            UserRole::Buyer => $this->leads->findMined($organizationId, $user->getId()),
            UserRole::Partner => $this->leads->findPurchased($organizationId, $user->getId()),
        };

        $buyers = [];
        foreach ($this->users->findByOrganization($organizationId) as $member) {
            $buyers[$member->getId()] = $member->getEmail();
        }

        $rows = [];
        foreach ($leads as $lead) {
            $rows[] = [
                'uuid' => $lead->getUuid(),
                'buyerId' => $lead->getBuyerId(),
                'buyerEmail' => $buyers[$lead->getBuyerId()] ?? '',
                'sellerId' => $lead->getSellerId(),
                'status' => $lead->getStatus()->value,
                'phone' => $lead->getPhone(),
                'email' => $lead->getEmail(),
                'firstName' => $lead->getFirstName(),
                'lastName' => $lead->getLastName(),
                'country' => $lead->getCountry(),
                'ip' => $lead->getIp(),
                'affSub' => $lead->getAffSub(),
                'priceCents' => $lead->getPriceCents(),
                'createdAt' => $lead->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }
}
