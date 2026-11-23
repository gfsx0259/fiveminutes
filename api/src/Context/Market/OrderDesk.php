<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Clock;
use App\Context\Lead\LiveDayInjector;
use App\Context\Lead\LiveDayInjectStore;
use App\Context\Lead\StockFulfillment;
use App\Context\Tree\OrderPool;
use App\Context\User\User;
use App\Context\User\UserRepository;
use App\Context\User\UserRole;
use App\Context\Wallet\InsufficientBalanceException;
use App\Context\Wallet\SettlementDesk;
use App\Value\Money;
use Cycle\ORM\EntityManagerInterface;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use Ramsey\Uuid\UuidFactoryInterface;

final readonly class OrderDesk
{
    public function __construct(
        private UuidFactoryInterface $uuids,
        private Clock $clock,
        private PresetRepository $presets,
        private OrderRepository $orders,
        private OrderPool $pool,
        private Warehouse $warehouse,
        private StockFulfillment $stock,
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private SettlementDesk $settlement,
        private OrganizationShelfAge $shelfAge,
        private StockPickPlan $pickPlan,
        private LiveDayInjector $liveDayInject,
        private LiveDayInjectStore $liveDayInjectStore,
    ) {}

    public function open(
        User $partner,
        string $presetId,
        string $geo,
        int $volume,
        int $priceCents,
        string $paymentModel,
        bool $stock,
        ?DateTimeImmutable $serviceOn,
        string $opensAt,
        string $closesAt,
        ?string $stockLeadGender = null,
        ?StockPickPreference $stockPick = null,
    ): Order {
        $stockPick ??= StockPickPreference::default();
        if ($partner->getRole() !== UserRole::Partner) {
            throw new DomainException('Only a partner can open an order');
        }
        $preset = $this->preset($partner, $presetId);
        if ($geo !== $preset->getCountry()) {
            throw new InvalidArgumentException('Geo must be the preset country');
        }
        $model = PaymentModel::from($paymentModel);
        if ($preset->getPaymentModel() !== $model->value) {
            throw new InvalidArgumentException('Payment model does not match the preset');
        }
        $base = $preset->getBasePriceCents();
        if ($stock) {
            $priceCents = $base;
        } else {
            $price = new Money($priceCents);
            if ($price->isBelow(new Money($base))) {
                throw new InvalidArgumentException('Price cannot be below the preset base price');
            }
        }
        $stockTotalCents = null;
        if ($stock) {
            $policy = $this->shelfAge->forOrganization($partner->getOrganizationId());
            $quote = $this->pickPlan->quote(
                $partner->getOrganizationId(),
                $preset,
                $volume,
                $policy,
                $stockLeadGender,
                $stockPick,
            );
            $stockTotalCents = $quote->totalCents;
        }

        $freshPartner = $this->users->findByPK($partner->getId());
        if (!$freshPartner instanceof User) {
            throw new DomainException('Partner was not found');
        }
        $affordCents = $stockTotalCents ?? ($priceCents * $volume);
        try {
            $this->settlement->assertPartnerCanAfford($freshPartner, new Money($affordCents));
        } catch (InsufficientBalanceException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }

        $order = new Order(
            $this->uuids->uuid4()->toString(),
            $partner->getOrganizationId(),
            $partner->getId(),
            $preset->getId(),
            $geo,
            $volume,
            $priceCents,
            $model->value,
            $stock,
            $stock ? null : $this->day($serviceOn),
            $opensAt,
            $closesAt,
            0,
            OrderStatus::InProgress->value,
            $this->clock->now(),
            $this->clock->now(),
            $stock ? $stockPick->priority->value : null,
            $stock ? $stockPick->zoneId : null,
        );
        $this->save($order);
        if ($stock) {
            $filled = $this->stock->fill($order, $stockLeadGender);
            if ($filled < $volume) {
                throw new InvalidArgumentException('Stock changed while the order was opening');
            }
        } else {
            $this->liveDayInject->scheduleForOrder($order, $preset);
        }

        return $order;
    }

    public function pause(User $actor, string $id): Order
    {
        $order = $this->owned($actor, $id);
        $order->pause($this->clock->now());
        $this->save($order);
        if (!$order->isStock()) {
            $this->liveDayInjectStore->cancelScheduledForOrder($order->getId());
        }

        return $order;
    }

    public function resume(User $actor, string $id): Order
    {
        $order = $this->owned($actor, $id);
        $order->resume($this->clock->now());
        $this->save($order);
        if (!$order->isStock() && $order->hasRoom() && $order->getStatus() === OrderStatus::InProgress) {
            $preset = $this->presets->findById($order->getPresetId());
            if ($preset instanceof Preset) {
                $this->liveDayInject->scheduleForOrder($order, $preset);
            }
        }

        return $order;
    }

    public function finish(User $actor, string $id): Order
    {
        $order = $this->owned($actor, $id);
        $order->finish($this->clock->now());
        $this->save($order);
        if (!$order->isStock()) {
            $this->liveDayInjectStore->cancelScheduledForOrder($order->getId());
        }

        return $order;
    }

    private function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->run();
        $this->pool->publish($order, $this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }

    private function preset(User $partner, string $presetId): Preset
    {
        $preset = $this->presets->findById($presetId);
        if (!$preset instanceof Preset || $preset->getOrganizationId() !== $partner->getOrganizationId()) {
            throw new PresetMissingException('Preset was not found');
        }

        return $preset;
    }

    private function owned(User $actor, string $id): Order
    {
        $order = $this->orders->findById($id);
        if (!$order instanceof Order || $order->getOrganizationId() !== $actor->getOrganizationId()) {
            throw new OrderMissingException('Order was not found');
        }
        $owner = $actor->getRole() === UserRole::Partner && $order->getPartnerId() === $actor->getId();
        if (!$owner && $actor->getRole() !== UserRole::Admin) {
            throw new DomainException('Only the partner who opened the order can change it');
        }

        return $order;
    }

    private function day(?DateTimeImmutable $serviceOn): DateTimeImmutable
    {
        if (!$serviceOn instanceof DateTimeImmutable) {
            throw new InvalidArgumentException('Date must look like 2026-11-16');
        }
        $day = $serviceOn->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $today = $now->format('Y-m-d');
        $tomorrow = $now->modify('+1 day')->format('Y-m-d');
        if ($day !== $today && $day !== $tomorrow) {
            throw new InvalidArgumentException('Order date must be today or tomorrow');
        }

        return $serviceOn;
    }
}
