<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Value\Geo;
use App\Value\Volume;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;

#[Entity(repository: OfferRepository::class, table: 'offers')]
class Offer
{
    #[Column(type: 'string')]
    private readonly string $geo;

    #[Column(type: 'string')]
    private string $status;

    #[Column(type: 'integer', name: 'supplied_count', typecast: 'int')]
    private int $suppliedCount;

    #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
    private DateTimeInterface $updatedAt;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'buyer_id')]
        private readonly string $buyerId,
        #[Column(type: 'uuid', name: 'preset_id')]
        private readonly string $presetId,
        string $geo,
        #[Column(type: 'integer', typecast: 'int')]
        private readonly int $volume,
        #[Column(type: 'boolean', typecast: 'bool')]
        private readonly bool $stock,
        #[Column(type: 'date', name: 'service_on', nullable: true, typecast: 'datetime')]
        private readonly ?DateTimeInterface $serviceOn,
        int $suppliedCount,
        string $status,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        if ($this->stock === ($this->serviceOn !== null)) {
            throw new InvalidArgumentException('A day offer has a date and a stock offer does not');
        }
        new Volume($this->volume);
        if ($suppliedCount < 0 || $suppliedCount > $this->volume) {
            throw new InvalidArgumentException('Supplied count must sit inside the volume');
        }
        $this->geo = (new Geo($geo))->toString();
        $this->suppliedCount = $suppliedCount;
        $this->status = OfferStatus::from($status)->value;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getBuyerId(): string
    {
        return $this->buyerId;
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

    public function isStock(): bool
    {
        return $this->stock;
    }

    public function getServiceOn(): ?DateTimeInterface
    {
        return $this->serviceOn;
    }

    public function getStatus(): OfferStatus
    {
        return OfferStatus::from($this->status);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function covers(string $presetId, string $geo, bool $stock, ?string $day): bool
    {
        if ($this->presetId !== $presetId || $this->geo !== strtoupper($geo) || $this->stock !== $stock) {
            return false;
        }
        if ($stock) {
            return true;
        }

        return $this->serviceOn !== null && $this->serviceOn->format('Y-m-d') === $day;
    }

    public function hasRoom(): bool
    {
        return $this->status === OfferStatus::Approved->value && $this->suppliedCount < $this->volume;
    }

    public function approve(DateTimeInterface $at): void
    {
        if ($this->status !== OfferStatus::Pending->value) {
            throw new DomainException('Offer is not pending');
        }
        $this->status = OfferStatus::Approved->value;
        $this->updatedAt = $at;
    }

    public function reject(DateTimeInterface $at): void
    {
        if ($this->status !== OfferStatus::Pending->value) {
            throw new DomainException('Offer is not pending');
        }
        $this->status = OfferStatus::Rejected->value;
        $this->updatedAt = $at;
    }

    public function trySupply(DateTimeInterface $at): bool
    {
        if (!$this->hasRoom()) {
            return false;
        }
        $this->suppliedCount++;
        if ($this->suppliedCount >= $this->volume) {
            $this->status = OfferStatus::Finished->value;
        }
        $this->updatedAt = $at;

        return true;
    }
}
