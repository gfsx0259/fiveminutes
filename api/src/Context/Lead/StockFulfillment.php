<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\Market\Order;
use App\Context\Market\PaymentModel;
use App\Context\Market\Preset;
use App\Context\Market\PresetMissingException;
use App\Context\Market\PresetRepository;
use App\Context\Wallet\SettlementDesk;
use App\Context\Tree\OrderPool;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class StockFulfillment
{
    public function __construct(
        private PresetRepository $presets,
        private LeadRepository $leads,
        private PlacementRepository $placements,
        private EntityManagerInterface $entityManager,
        private OrderPool $pool,
        private Clock $clock,
        private SettlementDesk $settlement,
    ) {}

    public function fill(Order $order, ?string $leadGender = null): int
    {
        if (!$order->isStock()) {
            return 0;
        }

        $preset = $this->presets->findById($order->getPresetId());
        if (!$preset instanceof Preset) {
            throw new PresetMissingException('Preset was not found');
        }

        $at = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $queued = $this->leads->findInStockForPreset(
            $order->getOrganizationId(),
            $preset,
            $order->getVolume(),
            $leadGender,
        );

        $filled = 0;
        foreach ($queued as $lead) {
            if (!$order->hasRoom()) {
                break;
            }
            $this->assign($lead, $order, PaymentModel::from($order->getPaymentModel()), $at);
            $order->supply($at);
            $filled++;
        }

        if ($filled > 0) {
            $this->entityManager->persist($order);
            $this->entityManager->run();
            $this->pool->publish($order, $at);
        }

        return $filled;
    }

    private function assign(Lead $lead, Order $order, PaymentModel $model, DateTimeImmutable $at): void
    {
        $sold = $lead->sell($order->getPartnerId(), $order->getId(), $order->getPriceCents(), $at);
        $settled = $this->settlement->settleStockLead($sold, $model);
        $placement = $this->placements->findByLead($lead->getUuid());
        if ($placement instanceof Placement) {
            $this->entityManager->delete($placement);
            $this->entityManager->run();
        }
        $this->entityManager->persist(new Placement(
            $lead->getUuid(),
            $settled->getOrderId(),
            $settled->getSellerId(),
            $settled->getPriceCents(),
            $settled->getSoldAt(),
            $at,
        ));
        $this->leads->add($settled);
    }
}