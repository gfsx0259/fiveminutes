<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Value\Geo;
use App\Value\Money;
use App\Value\Volume;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;
use ValueError;

#[Entity(repository: OrderRepository::class, table: 'orders')]
class Order
{
    #[Column(type: 'string')]
    private readonly string $geo;

    #[Column(type: 'time', name: 'opens_at')]
    private readonly DateTimeInterface $opensAt;

    #[Column(type: 'time', name: 'closes_at')]
    private readonly DateTimeInterface $closesAt;

    #[Column(type: 'string')]
    private string $status;

    #[Column(type: 'string', name: 'payment_model')]
    private readonly string $paymentModel;

    #[Column(type: 'integer', name: 'supplied_count', typecast: 'int')]
    private int $suppliedCount;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    private DateTimeInterface $updatedAt;

    #[Column(type: 'string', name: 'stock_pick_priority', nullable: true)]
    private readonly ?string $stockPickPriority;

    #[Column(type: 'string', name: 'stock_pick_zone', nullable: true)]
    private readonly ?string $stockPickZone;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'partner_id')]
        private readonly string $partnerId,
        #[Column(type: 'uuid', name: 'preset_id')]
        private readonly string $presetId,
        string $geo,
        #[Column(type: 'integer', typecast: 'int')]
        private readonly int $volume,
        #[Column(type: 'integer', name: 'price_cents', typecast: 'int')]
        private readonly int $priceCents,
        string $paymentModel,
        #[Column(type: 'boolean', typecast: 'bool')]
        private readonly bool $stock,
        #[Column(type: 'date', name: 'service_on', nullable: true, typecast: 'datetime')]
        private readonly ?DateTimeInterface $serviceOn,
        string $opensAt,
        string $closesAt,
        int $suppliedCount,
        string $status,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
        ?string $stockPickPriority = null,
        ?string $stockPickZone = null,
        #[Column(type: 'string', name: 'timezone')]
        private readonly string $timezone = 'UTC',
    ) {
        if ($this->stock === ($this->serviceOn !== null)) {
            throw new InvalidArgumentException('A day order has a date and a stock order does not');
        }
        if (!$this->stock && ($stockPickPriority !== null || $stockPickZone !== null)) {
            throw new InvalidArgumentException('Day orders do not carry stock pick preferences');
        }
        if ($this->stock) {
            StockPickPreference::fromStored($stockPickPriority, $stockPickZone);
        }
        new Volume($this->volume);
        new Money($this->priceCents);
        try {
            $this->paymentModel = PaymentModel::from($paymentModel)->value;
        } catch (ValueError) {
            throw new InvalidArgumentException('Payment model must be CPL or CPA');
        }
        if ($suppliedCount < 0 || $suppliedCount > $this->volume) {
            throw new InvalidArgumentException('Supplied count must sit inside the volume');
        }
        $window = new CallWindow($opensAt, $closesAt);
        try {
            $zone = new \DateTimeZone($this->timezone);
        } catch (\Exception) {
            throw new InvalidArgumentException('Timezone is not recognized');
        }
        if ($zone->getName() !== $this->timezone) {
            throw new InvalidArgumentException('Timezone is not recognized');
        }
        $this->geo = (new Geo($geo))->toString();
        $this->opensAt = $window->opensAt();
        $this->closesAt = $window->closesAt();
        $this->suppliedCount = $suppliedCount;
        $this->status = OrderStatus::from($status)->value;
        $this->updatedAt = $updatedAt;
        $this->stockPickPriority = $this->stock
            ? ($stockPickPriority ?? StockPickPriority::OldestFirst->value)
            : null;
        $this->stockPickZone = $this->stock && $stockPickZone !== null && $stockPickZone !== ''
            ? $stockPickZone
            : null;
    }

    public function stockPickPreference(): StockPickPreference
    {
        if (!$this->stock) {
            return StockPickPreference::default();
        }

        return StockPickPreference::fromStored($this->stockPickPriority, $this->stockPickZone);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    public function getPresetId(): string
    {
        return $this->presetId;
    }

    public function getGeo(): string
    {
        return $this->geo;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function getSuppliedCount(): int
    {
        return $this->suppliedCount;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getPaymentModel(): string
    {
        return $this->paymentModel;
    }

    public function isStock(): bool
    {
        return $this->stock;
    }

    public function getServiceOn(): ?DateTimeInterface
    {
        return $this->serviceOn;
    }

    public function getOpensAt(): string
    {
        return $this->opensAt->format('H:i');
    }

    public function getClosesAt(): string
    {
        return $this->closesAt->format('H:i');
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getStatus(): OrderStatus
    {
        return OrderStatus::from($this->status);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function hasRoom(): bool
    {
        return $this->status === OrderStatus::InProgress->value && $this->suppliedCount < $this->volume;
    }

    public function supply(DateTimeInterface $at): void
    {
        if (!$this->hasRoom()) {
            throw new DomainException('Order has no room');
        }
        $this->suppliedCount++;
        if ($this->suppliedCount >= $this->volume) {
            $this->status = OrderStatus::Finished->value;
        }
        $this->updatedAt = $at;
    }

    public function pause(DateTimeInterface $at): void
    {
        if ($this->status !== OrderStatus::InProgress->value) {
            throw new DomainException('Order is not in progress');
        }
        $this->status = OrderStatus::Paused->value;
        $this->updatedAt = $at;
    }

    public function resume(DateTimeInterface $at): void
    {
        if ($this->status !== OrderStatus::Paused->value) {
            throw new DomainException('Order is not paused');
        }
        $this->status = $this->suppliedCount >= $this->volume
            ? OrderStatus::Finished->value
            : OrderStatus::InProgress->value;
        $this->updatedAt = $at;
    }

    public function finish(DateTimeInterface $at): void
    {
        if ($this->status === OrderStatus::Finished->value) {
            throw new DomainException('Order is already finished');
        }
        $this->status = OrderStatus::Finished->value;
        $this->updatedAt = $at;
    }
}
