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

#[Entity(repository: SlotRepository::class, table: 'slots')]
class Slot
{
    use HasListingStatus;

    #[Column(type: 'uuid', primary: true)]
    private string $id;

    #[Column(type: 'uuid', name: 'organization_id')]
    private string $organizationId;

    #[Column(type: 'uuid', name: 'buyer_id')]
    private string $buyerId;

    #[Column(type: 'string')]
    private string $geo;

    #[Column(type: 'integer', typecast: 'int')]
    private int $volume;

    #[Column(type: 'integer', name: 'price_cents', typecast: 'int')]
    private int $priceCents;

    #[Column(type: 'string', name: 'pricing_model')]
    private string $pricingModel;

    #[Column(type: 'datetime', typecast: 'datetime')]
    private DateTimeInterface $deadline;

    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    private DateTimeInterface $createdAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $buyerId,
        string $geo,
        int $volume,
        int $priceCents,
        string $pricingModel,
        DateTimeInterface $deadline,
        string $status,
        DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        if ($deadline <= $createdAt) {
            throw new \InvalidArgumentException('Deadline must be after the slot is opened');
        }

        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->buyerId = $buyerId;
        $this->geo = (new Geo($geo))->toString();
        $this->volume = (new Volume($volume))->toInt();
        $this->priceCents = (new Money($priceCents))->cents();
        $this->pricingModel = PricingModel::from($pricingModel)->value;
        $this->deadline = $deadline;
        $this->status = ListingStatus::from($status)->value;
        $this->createdAt = $createdAt;
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
