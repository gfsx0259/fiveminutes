<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\Market\OrderMissingException;
use App\Context\Market\OrderRepository;
use App\Context\Market\PaymentModel;
use App\Context\User\User;
use App\Context\User\UserRole;
use App\Context\Wallet\SettlementDesk;
use Cycle\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;

final readonly class LeadConversionDesk
{
    public function __construct(
        private LeadRepository $leads,
        private OrderRepository $orders,
        private SettlementDesk $settlement,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    public function approve(User $actor, string $uuid): Lead
    {
        $lead = $this->ownedLead($actor, $uuid);
        $this->requireCpaSold($lead);
        $updated = $lead->withStatus(LeadStatus::Approved, $this->clock);
        $this->settlement->settleCpaApproved($updated);
        $this->leads->add($updated);
        $this->entityManager->persist(new LeadConversionNote(
            $uuid,
            LeadStatus::Approved->value,
            '',
            $actor->getId(),
            $this->clock->now(),
        ));
        $this->entityManager->run();

        return $updated;
    }

    public function reject(User $actor, string $uuid, string $reason): Lead
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Reject reason is required');
        }
        $lead = $this->ownedLead($actor, $uuid);
        $this->requireCpaSold($lead);
        $updated = $lead->withStatus(LeadStatus::Rejected, $this->clock);
        $this->leads->add($updated);
        $this->entityManager->persist(new LeadConversionNote(
            $uuid,
            LeadStatus::Rejected->value,
            $reason,
            $actor->getId(),
            $this->clock->now(),
        ));
        $this->entityManager->run();

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
        if ($actor->getRole() === UserRole::Buyer && $lead->getBuyerId() === $actor->getId()) {
            return $lead;
        }

        throw new DomainException('You cannot change this lead');
    }

    private function requireCpaSold(Lead $lead): void
    {
        if ($lead->getStatus() !== LeadStatus::Sold) {
            throw new DomainException('Lead must be sold before conversion');
        }
        $orderId = $lead->getOrderId();
        if ($orderId === null) {
            throw new DomainException('Lead has no order');
        }
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            throw new OrderMissingException('Order was not found');
        }
        if (PaymentModel::from($order->getPaymentModel()) !== PaymentModel::Cpa) {
            throw new DomainException('Conversion is only for CPA leads');
        }
    }
}