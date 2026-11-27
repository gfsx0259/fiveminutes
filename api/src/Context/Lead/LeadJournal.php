<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\Market\OrganizationMarkup;
use App\Context\Team\Permission;
use App\Context\Team\Seat;
use App\Context\Team\TeamOrders;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use App\Value\MetaMap;

final readonly class LeadJournal
{
    public function __construct(
        private LeadRepository $leads,
        private UserRepository $users,
        private OrganizationMarkup $markup,
        private LiveDayInjectStore $liveInject,
        private TeamOrders $teamOrders,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forSeat(Seat $seat): array
    {
        $user = $seat->getAccount();
        $organizationId = $user->getOrganizationId();
        $visible = $this->teamOrders->visibleOrderIds($seat);
        $leads = match ($user->getRole()) {
            UserRole::Buyer => $this->leads->findMined($organizationId, $user->getId()),
            UserRole::Partner => $visible === null
                ? $this->leads->findPurchased($organizationId, $user->getId())
                : $this->leads->findPurchasedInOrders($organizationId, $user->getId(), array_keys($visible)),
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
            $rows[] = $this->row($lead, $emails, $seat, $overlay[$lead->getUuid()] ?? null);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function oneForSeat(Seat $seat, string $uuid): ?array
    {
        $user = $seat->getAccount();
        $lead = $this->leads->findByUuid($uuid);
        if ($lead === null || !$this->allows($user, $lead) || !$this->teamOrders->sees($seat, $lead->getOrderId())) {
            return null;
        }

        $overlay = $user->getRole() === UserRole::Admin
            ? $this->liveInject->overlayByLeadUuids([$uuid])[$uuid] ?? null
            : null;

        return $this->row($lead, $this->emails($user->getOrganizationId()), $seat, $overlay);
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
    private function row(Lead $lead, array $emails, Seat $seat, ?array $inject = null): array
    {
        $viewer = $seat->getAccount();
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
            'meta' => MetaMap::from($lead->getMeta())->forJson(),
            'presetId' => $lead->getPresetId(),
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

        return $this->redact($row, $seat);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function redact(array $row, Seat $seat): array
    {
        $viewer = $seat->getAccount();
        $row['contactsHidden'] = !$seat->can(Permission::LeadsView);
        if ($row['contactsHidden']) {
            $row['phone'] = null;
            $row['email'] = null;
        }
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