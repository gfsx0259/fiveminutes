<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Clock;
use App\Context\Market\Order;
use App\Context\Market\OrganizationShelfAge;
use App\Context\Market\PaymentModel;
use App\Context\Market\Preset;
use App\Context\Market\PresetMissingException;
use App\Context\Market\PresetRepository;
use App\Context\Market\StockPickPlan;
use App\Context\Wallet\SettlementDesk;
use App\Context\Tree\OrderPool;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class StockFulfillment
{
    public function __construct(
        private PresetRepository $presets,
        private StockPickPlan $pickPlan,
        private OrganizationShelfAge $shelfAge,
        private PlacementRepository $placements,
        private EntityManagerInterface $entityManager,
        private OrderPool $pool,
        private Clock $clock,
        private SettlementDesk $settlement,
        private LeadRepository $leads,
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

        $policy = $this->shelfAge->forOrganization($order->getOrganizationId());
        $remaining = $order->getVolume() - $order->getSuppliedCount();
        if ($remaining < 1) {
            return 0;
        }

        $quote = $this->pickPlan->quote(
            $order->getOrganizationId(),
            $preset,
            $remaining,
            $policy,
            $leadGender,
        );

        $at = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $filled = 0;
        foreach ($quote->picks as $pick) {
            if (!$order->hasRoom()) {
                break;
            }
            $this->assign($pick['lead'], $order, PaymentModel::from($order->getPaymentModel()), $at, $pick['effectivePriceCents']);
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

    private function assign(
        Lead $lead,
        Order $order,
        PaymentModel $model,
        DateTimeImmutable $at,
        int $priceCents,
    ): void {
        $sold = $lead->sell($order->getPartnerId(), $order->getId(), $priceCents, $at);
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