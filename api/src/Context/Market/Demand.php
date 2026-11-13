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

#[Entity(repository: DemandRepository::class, table: 'demands')]
class Demand
{
    use HasListingStatus;

    #[Column(type: 'uuid', primary: true)]
    private string $id;

    #[Column(type: 'uuid', name: 'organization_id')]
    private string $organizationId;

    #[Column(type: 'uuid', name: 'partner_id')]
    private string $partnerId;

    #[Column(type: 'string')]
    private string $geo;

    #[Column(type: 'integer', typecast: 'int')]
    private int $volume;

    #[Column(type: 'integer', name: 'price_cents', typecast: 'int')]
    private int $priceCents;

    #[Column(type: 'string', name: 'pricing_model')]
    private string $pricingModel;

    #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
    private DateTimeInterface $createdAt;

    public function __construct(
        string $id,
        string $organizationId,
        string $partnerId,
        string $geo,
        int $volume,
        int $priceCents,
        string $pricingModel,
        string $status,
        DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->partnerId = $partnerId;
        $this->geo = (new Geo($geo))->toString();
        $this->volume = (new Volume($volume))->toInt();
        $this->priceCents = (new Money($priceCents))->cents();
        $this->pricingModel = PricingModel::from($pricingModel)->value;
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

    public function getPartnerId(): string
    {
        return $this->partnerId;
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

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function close(User $actor, ListingTransition $transition): void
    {
        $this->closeAsAuthor($actor->getId(), $this->partnerId, $transition);
    }
}
