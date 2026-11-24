<?php

declare(strict_types=1);

namespace App\Context\Wallet;

use App\Clock;
use App\Context\Lead\Lead;
use App\Context\Lead\LeadStatus;
use App\Context\Market\OrganizationMarkup;
use App\Context\Market\PaymentModel;
use App\Context\Organization\OrganizationRepository;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Value\Money;
use Cycle\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;

final readonly class SettlementDesk
{
    public function __construct(
        private UserRepository $users,
        private OrganizationRepository $organizations,
        private OrganizationMarkup $markup,
        private WalletLedger $ledger,
        private WalletSettlementClaimRepository $claims,
        private Clock $clock,
        private EntityManagerInterface $entityManager,
    ) {}

    public function assertPartnerCanAfford(User $partner, Money $total): void
    {
        if (!$partner->getBalance()->covers($total)) {
            throw new InsufficientBalanceException('Your balance is not enough for this order');
        }
    }

    public function settleCplDelivered(Lead $lead): void
    {
        $sellerId = $lead->getSellerId();
        $orderId = $lead->getOrderId();
        if ($sellerId === null || $orderId === null) {
            throw new InvalidArgumentException('CPL settlement needs a sold lead');
        }
        if ($this->claims->exists($lead->getUuid(), SettlementKind::CplDelivered)) {
            return;
        }

        $catalog = new Money($lead->getPriceCents());
        $pricing = $this->markup->forOrganization($lead->getOrganizationId());
        $fee = new Money($pricing->platformFeeCents($catalog->cents()));
        $net = new Money($pricing->buyerNetCents($catalog->cents()));

        $partner = $this->requireUser($sellerId);
        $buyer = $this->requireUser($lead->getBuyerId());
        $organization = $this->requireOrganization($lead->getOrganizationId());

        $this->entityManager->persist(new WalletSettlementClaim(
            $lead->getUuid(),
            SettlementKind::CplDelivered->value,
            $this->clock->now(),
        ));

        $this->ledger->debitUser($partner, $catalog, TransactionKind::PartnerLeadCharge, $lead->getUuid(), $orderId);
        $this->ledger->creditUser($buyer, $net, TransactionKind::BuyerLeadCredit, $lead->getUuid(), $orderId);
        if ($fee->cents() > 0) {
            $this->ledger->creditOrganization(
                $organization,
                $fee,
                TransactionKind::OrgPlatformFee,
                $lead->getUuid(),
                $orderId,
            );
        }
        $this->ledger->flush();
    }

    public function settleCpaSold(Lead $lead): void
    {
        $sellerId = $lead->getSellerId();
        $orderId = $lead->getOrderId();
        if ($sellerId === null || $orderId === null) {
            throw new InvalidArgumentException('CPA partner charge needs a sold lead');
        }
        if ($this->claims->exists($lead->getUuid(), SettlementKind::CpaPartnerCharge)) {
            return;
        }

        $catalog = new Money($lead->getPriceCents());
        $partner = $this->requireUser($sellerId);

        $this->entityManager->persist(new WalletSettlementClaim(
            $lead->getUuid(),
            SettlementKind::CpaPartnerCharge->value,
            $this->clock->now(),
        ));
        $this->ledger->debitUser($partner, $catalog, TransactionKind::PartnerLeadCharge, $lead->getUuid(), $orderId);
        $this->ledger->flush();
    }

    public function settleCpaApproved(Lead $lead): void
    {
        $orderId = $lead->getOrderId();
        if ($orderId === null) {
            throw new InvalidArgumentException('CPA approval needs a sold lead');
        }
        if ($this->claims->exists($lead->getUuid(), SettlementKind::CpaApprovedPayout)) {
            return;
        }
        if (!$this->claims->exists($lead->getUuid(), SettlementKind::CpaPartnerCharge)) {
            throw new DomainException('CPA payout before partner charge');
        }

        $catalog = new Money($lead->getPriceCents());
        $pricing = $this->markup->forOrganization($lead->getOrganizationId());
        $fee = new Money($pricing->platformFeeCents($catalog->cents()));
        $net = new Money($pricing->buyerNetCents($catalog->cents()));

        $buyer = $this->requireUser($lead->getBuyerId());
        $organization = $this->requireOrganization($lead->getOrganizationId());

        $this->entityManager->persist(new WalletSettlementClaim(
            $lead->getUuid(),
            SettlementKind::CpaApprovedPayout->value,
            $this->clock->now(),
        ));
        $this->ledger->creditUser($buyer, $net, TransactionKind::BuyerLeadCredit, $lead->getUuid(), $orderId);
        if ($fee->cents() > 0) {
            $this->ledger->creditOrganization(
                $organization,
                $fee,
                TransactionKind::OrgPlatformFee,
                $lead->getUuid(),
                $orderId,
            );
        }
        $this->ledger->flush();
    }

    public function settleStockLead(Lead $sold, PaymentModel $model): Lead
    {
        if ($model === PaymentModel::Cpl) {
            $delivered = $sold->withStatus(LeadStatus::Delivered, $this->clock);
            $this->settleCplDelivered($delivered);

            return $delivered;
        }

        $this->settleCpaSold($sold);

        return $sold;
    }

    private function requireUser(string $id): User
    {
        $user = $this->users->findByPK($id);
        if (!$user instanceof User) {
            throw new DomainException('User was not found for settlement');
        }

        return $user;
    }

    private function requireOrganization(string $id): \App\Context\Organization\Organization
    {
        $organization = $this->organizations->findById($id);
        if ($organization === null) {
            throw new DomainException('Organization was not found for settlement');
        }

        return $organization;
    }
}