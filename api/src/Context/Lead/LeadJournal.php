<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\Market\OrganizationMarkup;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;

final readonly class LeadJournal
{
    public function __construct(
        private LeadRepository $leads,
        private UserRepository $users,
        private OrganizationMarkup $markup,
        private LiveDayInjectStore $liveInject,
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
            UserRole::Admin => $this->leads->findByOrganization($organizationId),
        };

        $emails = $this->emails($organizationId);
        $leadList = [];
        foreach ($leads as $lead) {
            $leadList[] = $lead;
        }
        $overlay = $user->getRole() === UserRole::Admin
            ? $this->liveInject->overlayByLeadUuids(array_map(static fn (Lead $lead): string => $lead->getUuid(), $leadList))
            : [];
        $rows = [];
        foreach ($leadList as $lead) {
            $rows[] = $this->row($lead, $emails, $user, $overlay[$lead->getUuid()] ?? null);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function oneForUser(User $user, string $uuid): ?array
    {
        $lead = $this->leads->findByUuid($uuid);
        if ($lead === null || !$this->allows($user, $lead)) {
            return null;
        }

        $overlay = $user->getRole() === UserRole::Admin
            ? $this->liveInject->overlayByLeadUuids([$uuid])[$uuid] ?? null
            : null;

        return $this->row($lead, $this->emails($user->getOrganizationId()), $user, $overlay);
    }

    /**
     * @return array<string, string>
     */
    private function emails(string $organizationId): array
    {
        $emails = [];
        foreach ($this->users->findByOrganization($organizationId) as $member) {
            $emails[$member->getId()] = $member->getEmail();
        }

        return $emails;
    }

    /**
     * @param array<string, string> $emails
     * @return array<string, mixed>
     */
    /**
     * @param ?array{status: string, due_at: string, order_id: string} $inject
     */
    private function row(Lead $lead, array $emails, User $viewer, ?array $inject = null): array
    {
        $sellerId = $lead->getSellerId();

        $row = [
            'uuid' => $lead->getUuid(),
            'buyerId' => $lead->getBuyerId(),
            'buyerEmail' => $emails[$lead->getBuyerId()] ?? '',
            'sellerId' => $sellerId,
            'sellerEmail' => $sellerId === null ? '' : ($emails[$sellerId] ?? ''),
            'orderId' => $lead->getOrderId(),
            'status' => $lead->getStatus()->value,
            'phone' => $lead->getPhone(),
            'email' => $lead->getEmail(),
            'firstName' => $lead->getFirstName(),
            'lastName' => $lead->getLastName(),
            'country' => $lead->getCountry(),
            'trafficSource' => $lead->getTrafficSource(),
            'age' => $lead->getAge(),
            'gender' => $lead->getGender(),
            'ip' => $lead->getIp(),
            'affSub' => $lead->getAffSub(),
            'uniqId' => $lead->getUniqId(),
            'priceCents' => $lead->getPriceCents(),
            'createdAt' => $lead->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $lead->getUpdatedAt()->format(DATE_ATOM),
            'soldAt' => $lead->getSoldAt()?->format(DATE_ATOM),
            'isDelivered' => $lead->isDelivered(),
            'deliveryError' => $lead->hasDeliveryError(),
            'clientStatus' => $lead->getClientStatus(),
            'clientDeposit' => $lead->hasClientDeposit(),
        ];

        $pricing = $this->markup->forOrganization($viewer->getOrganizationId());
        $catalogCents = $lead->getPriceCents();
        $row['buyerNetCents'] = $pricing->buyerNetCents($catalogCents);

        if ($viewer->getRole() === UserRole::Partner) {
            $row['partnerPriceCents'] = $pricing->partnerPriceCents($catalogCents);
        }
        if ($viewer->getRole() === UserRole::Admin) {
            $row['platformFeeCents'] = $pricing->platformFeeCents($catalogCents);
            if ($inject !== null) {
                $row['liveInjectStatus'] = $inject['status'];
                $row['liveInjectDueAt'] = (new \DateTimeImmutable($inject['due_at']))->format(DATE_ATOM);
                $row['liveInjectOrderId'] = $inject['order_id'];
            }
        }

        return $this->redact($row, $viewer);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function redact(array $row, User $viewer): array
    {
        if ($viewer->getRole() === UserRole::Buyer) {
            unset($row['sellerId'], $row['sellerEmail'], $row['orderId']);
        } elseif ($viewer->getRole() === UserRole::Partner) {
            unset($row['buyerId'], $row['buyerEmail'], $row['buyerNetCents']);
        }

        return $row;
    }

    private function allows(User $user, Lead $lead): bool
    {
        if ($lead->getOrganizationId() !== $user->getOrganizationId()) {
            return false;
        }

        return match ($user->getRole()) {
            UserRole::Admin => true,
            UserRole::Buyer => $lead->getBuyerId() === $user->getId(),
            UserRole::Partner => $lead->getSellerId() === $user->getId(),
        };
    }
}