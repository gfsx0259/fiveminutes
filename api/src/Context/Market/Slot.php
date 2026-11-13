<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\User\User;
use App\Value\Geo;
use App\Value\Money;
use App\Value\Volume;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use InvalidArgumentException;

#[Entity(repository: SlotRepository::class, table: 'slots')]
class Slot
{
    use HasListingStatus;

    #[Column(type: 'string')]
    private readonly string $geo;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'buyer_id')]
        private readonly string $buyerId,
        string $geo,
        #[Column(type: 'integer', typecast: 'int')]
        private readonly int $volume,
        #[Column(type: 'integer', name: 'price_cents', typecast: 'int')]
        private readonly int $priceCents,
        #[Column(type: 'string', name: 'pricing_model')]
        private readonly string $pricingModel,
        #[Column(type: 'datetime', typecast: 'datetime')]
        private readonly DateTimeInterface $deadline,
        string $status,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        if ($this->deadline <= $this->createdAt) {
            throw new InvalidArgumentException('Deadline must be after the slot is opened');
        }
        new Volume($this->volume);
        new Money($this->priceCents);
        PricingModel::from($this->pricingModel);

        $this->geo = (new Geo($geo))->toString();
        $this->status = ListingStatus::from($status)->value;
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

    public function getGeo(): string
    {
        return $this->geo;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function getPricingModel(): PricingModel
    {
        return PricingModel::from($this->pricingModel);
    }

    public function getDeadline(): DateTimeInterface
    {
        return $this->deadline;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function close(User $actor, ListingTransition $transition): void
    {
        $this->closeAsAuthor($actor->getId(), $this->buyerId, $transition);
    }
}
