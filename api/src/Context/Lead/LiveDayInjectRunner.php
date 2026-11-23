<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\Market\Order;
use App\Context\Market\OrderRepository;
use App\Context\Market\OrderStatus;
use App\Context\Tree\OrderPool;
use App\Context\Wallet\SettlementPublisher;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LiveDayInjectRunner
{
    public function __construct(
        private LiveDayInjectStore $store,
        private LeadRepository $leads,
        private OrderRepository $orders,
        private PlacementRepository $placements,
        private EntityManagerInterface $entityManager,
        private OrderPool $pool,
        private SettlementPublisher $settlements,
        private Clock $clock,
    ) {}

    public function publishDue(int $limit = 20): int
    {
        $claimed = $this->store->claimDue($limit);
        $published = 0;
        foreach ($claimed as $row) {
            if ($this->publishOne($row['id'], $row['order_id'], $row['lead_uuid'])) {
                $published++;
            }
        }

        return $published;
    }

    private function publishOne(int $itemId, string $orderId, string $leadUuid): bool
    {
        $order = $this->orders->findById($orderId);
        if (!$order instanceof Order) {
            $this->store->markFailed($itemId);

            return false;
        }
        if ($order->isStock() || $order->getStatus() !== OrderStatus::InProgress || !$order->hasRoom()) {
            $this->store->markSkipped($itemId);

            return false;
        }

        $lead = $this->leads->findByUuid($leadUuid);
        if ($lead === null || $lead->getStatus() !== LeadStatus::InStock) {
            $this->store->markFailed($itemId);

            return false;
        }

        $at = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $priceCents = $order->getPriceCents();
        $sold = $lead->sell($order->getPartnerId(), $order->getId(), $priceCents, $at);
        $order->supply($at);

        $placement = $this->placements->findByLead($lead->getUuid());
        if ($placement instanceof Placement) {
            $this->entityManager->delete($placement);
            $this->entityManager->run();
        }
        $this->entityManager->persist(new Placement(
            $lead->getUuid(),
            $sold->getOrderId(),
            $sold->getSellerId(),
            $sold->getPriceCents(),
            $sold->getSoldAt(),
            $at,
        ));
        $this->entityManager->persist($order);
        $this->entityManager->run();

        $this->leads->add($sold);
        $this->pool->publish($order, $at);
        $this->settlements->publishMatched($sold, $order);

        return true;
    }
}