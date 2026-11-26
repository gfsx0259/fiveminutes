<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use App\Context\Market\Order;
use App\Context\Market\OrderMissingException;
use App\Context\Market\OrderRepository;
use App\Context\User\UserRole;
use Cycle\ORM\EntityManagerInterface;

final readonly class TeamOrders
{
    public function __construct(
        private TeamUnitRepository $units,
        private OrderPlacementRepository $placements,
        private OrderRepository $orders,
        private TeamBudget $budget,
        private TeamJournal $journal,
        private EntityManagerInterface $entityManager,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, true>|null
     */
    public function visibleOrderIds(Seat $seat): ?array
    {
        if ($seat->unitId() === null) {
            return null;
        }

        $accountId = $seat->getAccount()->getId();
        $unitIds = array_map(
            static fn (TeamUnit $unit): string => $unit->getId(),
            $this->units->treeOf($accountId)->within($seat->unitId()),
        );

        return array_fill_keys($this->placements->orderIdsIn($accountId, $unitIds), true);
    }

    public function sees(Seat $seat, ?string $orderId): bool
    {
        $visible = $this->visibleOrderIds($seat);

        return $visible === null || ($orderId !== null && isset($visible[$orderId]));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function present(Seat $seat, array $rows): array
    {
        $account = $seat->getAccount();
        if ($account->getRole() !== UserRole::Partner) {
            return $rows;
        }

        $tree = $this->units->treeOf($account->getId());
        $placements = $this->placements->byOrderOf($account->getId());
        $visible = [];
        foreach ($rows as $row) {
            $placement = $placements[(string) ($row['id'] ?? '')] ?? null;
            if (!$tree->covers($seat->unitId(), $placement?->getUnitId())) {
                continue;
            }
            $row['teamId'] = $placement?->getUnitId();
            $row['openedBy'] = $placement?->getOpenedBy();
            $visible[] = $row;
        }

        return $visible;
    }

    public function unitForNewOrder(Seat $seat, ?string $requested): ?string
    {
        $unitId = $requested ?? $seat->unitId();
        $tree = $this->units->treeOf($seat->getAccount()->getId());
        if (!$tree->reaches($seat->unitId(), $unitId)) {
            throw new TeamUnitMissingException();
        }
        if (!$tree->covers($seat->unitId(), $unitId)) {
            throw new NotTeamLeadException('You cannot open orders above your team');
        }

        return $unitId;
    }

    public function place(Seat $seat, Order $order, ?string $unitId): void
    {
        $now = $this->clock->now();
        $this->entityManager->persist(new OrderPlacement(
            $order->getId(),
            $seat->getAccount()->getId(),
            $unitId,
            $seat->email(),
            $now,
            $now,
        ));
        $this->journal->record($seat->getAccount(), $seat->email(), TeamAction::OrderOpened, $unitId, [
            'order' => $order->getId(),
            'volume' => $order->getVolume(),
            'priceCents' => $order->getPriceCents(),
        ]);
        $this->entityManager->run();
    }

    public function move(Seat $seat, string $orderId, ?string $unitId): void
    {
        if (!$seat->manages()) {
            throw new NotTeamLeadException('Only a team lead moves orders between teams');
        }

        $account = $seat->getAccount();
        $order = $this->orders->findByPK($orderId);
        if (!$order instanceof Order || $order->getPartnerId() !== $account->getId()) {
            throw new OrderMissingException('Order not found');
        }

        $tree = $this->units->treeOf($account->getId());
        if (!$tree->reaches($seat->unitId(), $unitId)) {
            throw new TeamUnitMissingException();
        }

        $placement = $this->placements->byOrderOf($account->getId())[$orderId] ?? null;
        if (!$tree->covers($seat->unitId(), $placement?->getUnitId()) || !$tree->covers($seat->unitId(), $unitId)) {
            throw new NotTeamLeadException('You cannot move orders outside your team');
        }

        $from = $placement?->getUnitId();
        if ($from === $unitId) {
            return;
        }

        $this->budget->guard($account, function () use ($account, $orderId, $unitId, $from, $placement, $order, $seat, $tree): void {
            $this->budget->assertFits($account, $unitId, fn (): int => $this->budget->orderCommitmentCents($orderId), $from);

            $now = $this->clock->now();
            $placement ??= new OrderPlacement($orderId, $account->getId(), null, $account->getEmail(), $order->getCreatedAt(), $now);
            $placement->moveTo($unitId, $this->clock);
            $this->entityManager->persist($placement);
            $this->journal->record($account, $seat->email(), TeamAction::OrderMoved, $unitId, [
                'order' => $orderId,
                'from' => $tree->find($from)?->getName(),
                'to' => $tree->find($unitId)?->getName(),
            ]);
            $this->entityManager->run();
        });
    }
}
