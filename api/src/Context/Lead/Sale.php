<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\Market\Order;
use App\Context\Market\OrderRepository;
use App\Context\Market\Permit;
use App\Context\Market\Preset;
use App\Context\Market\PresetMatch;
use App\Context\Wallet\SettlementPublisher;
use App\Context\Tree\OrderPool;
use App\Context\Tree\Pick;
use App\Context\Tree\Tree;
use App\Clock;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PDOException;
use Throwable;

final readonly class Sale
{
    public function __construct(
        private Tree $tree,
        private PresetMatch $presets,
        private OrderRepository $orders,
        private OrderPool $pool,
        private Permit $permits,
        private PlacementRepository $placements,
        private LeadRepository $leads,
        private EntityManagerInterface $entityManager,
        private ORMInterface $orm,
        private Clock $clock,
        private SettlementPublisher $settlements,
    ) {}

    public function place(Lead $lead): Lead
    {
        if ($lead->isTest()) {
            // A test lead proves the delivery chain: it is kept for its buyer, never sold, shelved or settled.
            $this->leads->add($lead);

            return $lead;
        }

        $this->orm->getHeap()->clean();
        $existing = $this->placements->findByLead($lead->getUuid());
        if ($existing !== null) {
            $previous = $this->leads->findByUuid($lead->getUuid());
            $lead = $previous instanceof Lead
                ? $lead->withPreset($previous->getPresetId())
                : $lead->withPreset(($preset = $this->presets->forLead($lead)) instanceof Preset ? $preset->getId() : null);
            $stored = $this->stored($lead, $existing);
            $this->leads->add($stored);

            return $stored;
        }

        $at = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $pick = $this->tree->take($lead, $at);
        $order = $this->usable($lead, $pick);
        if ($order instanceof Order) {
            $lead = $lead->withPreset($order->getPresetId());
        } else {
            $this->giveBack($lead, $pick);
            $pick = null;
            $order = null;
            $preset = $this->presets->forLead($lead);
            $lead = $lead->withPreset($preset instanceof Preset ? $preset->getId() : null);
        }
        $stored = $order === null ? $lead->hold($at) : $lead->sell($order->getPartnerId(), $order->getId(), $order->getPriceCents(), $at);
        if ($order !== null) {
            $order->supply($at);
            $this->entityManager->persist($order);
        }
        $this->entityManager->persist(new Placement(
            $lead->getUuid(),
            $stored->getOrderId(),
            $stored->getSellerId(),
            $stored->getPriceCents(),
            $stored->getSoldAt(),
            $at,
        ));
        $this->permits->consume($lead, $order === null ? null : $pick);

        try {
            $this->entityManager->run();
        } catch (Throwable $exception) {
            $this->orm->getHeap()->clean();
            $this->giveBack($lead, $pick);
            if (!$this->duplicate($exception)) {
                throw $exception;
            }
            $existing = $this->placements->findByLead($lead->getUuid());
            if ($existing === null) {
                throw $exception;
            }
            $stored = $this->stored($lead, $existing);
            $this->leads->add($stored);

            return $stored;
        }

        if ($order !== null) {
            $this->pool->publish($order, $at);
            $this->settlements->publishMatched($stored, $order);
        }
        $this->leads->add($stored);

        return $stored;
    }

    private function usable(Lead $lead, ?Pick $pick): ?Order
    {
        if ($pick === null) {
            return null;
        }
        $order = $this->orders->findById($pick->orderId);
        if ($order instanceof Order && $order->hasRoom() && $order->getOrganizationId() === $lead->getOrganizationId()) {
            return $order;
        }

        return null;
    }

    private function giveBack(Lead $lead, ?Pick $pick): void
    {
        if ($pick === null) {
            return;
        }
        $this->pool->release($lead->getOrganizationId(), $pick->orderId, $pick->localDay);
    }

    private function stored(Lead $lead, Placement $placement): Lead
    {
        $soldAt = $placement->getSoldAt();
        if ($soldAt === null || $placement->getSellerId() === null || $placement->getOrderId() === null) {
            return $lead->hold($this->instant($placement->getCreatedAt()));
        }

        return $lead->sell(
            $placement->getSellerId(),
            $placement->getOrderId(),
            $placement->getPriceCents(),
            $this->instant($soldAt),
        );
    }

    private function instant(DateTimeInterface $at): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($at)->setTimezone(new DateTimeZone('UTC'));
    }

    private function duplicate(Throwable $exception): bool
    {
        while ($exception !== null) {
            if ($exception instanceof PDOException && ($exception->errorInfo[0] ?? null) === '23505') {
                return true;
            }
            if (str_contains($exception->getMessage(), '23505')) {
                return true;
            }
            $exception = $exception->getPrevious();
        }

        return false;
    }
}
