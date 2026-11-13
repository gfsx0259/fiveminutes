<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Context\User\User;
use App\Value\Geo;
use App\Value\Money;
use App\Value\Volume;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

#[Entity(repository: DemandRepository::class, table: 'demands')]
class Demand
{
    use HasListingStatus;

    #[Column(type: 'string')]
    private readonly string $geo;

    #[Column(type: 'time', name: 'opens_at')]
    private readonly DateTimeInterface $opensAt;

    #[Column(type: 'time', name: 'closes_at')]
    private readonly DateTimeInterface $closesAt;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'partner_id')]
        private readonly string $partnerId,
        string $geo,
        #[Column(type: 'integer', typecast: 'int')]
        private readonly int $volume,
        #[Column(type: 'integer', name: 'price_cents', typecast: 'int')]
        private readonly int $priceCents,
        #[Column(type: 'string', name: 'pricing_model')]
        private readonly string $pricingModel,
        #[Column(type: 'date', name: 'service_on', typecast: 'datetime')]
        private readonly DateTimeInterface $serviceOn,
        string $opensAt,
        string $closesAt,
        string $status,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        DateTimeInterface $updatedAt,
    ) {
        $opensAt = $this->clock($opensAt);
        $closesAt = $this->clock($closesAt);
        if ($opensAt >= $closesAt) {
            throw new InvalidArgumentException('Call center must close after it opens');
        }
        new Volume($this->volume);
        new Money($this->priceCents);
        PricingModel::from($this->pricingModel);

        $this->geo = (new Geo($geo))->toString();
        $this->opensAt = $this->moment($opensAt);
        $this->closesAt = $this->moment($closesAt);
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

    public function getServiceOn(): DateTimeInterface
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

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function close(User $actor, ListingTransition $transition): void
    {
        $this->closeAsAuthor($actor->getId(), $this->partnerId, $transition);
    }

    private function clock(string $value): string
    {
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            throw new InvalidArgumentException('Call center hour must be HH:MM');
        }

        return $value . ':00';
    }

    private function moment(string $value): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat('!H:i:s', $value);
        if ($time === false) {
            throw new InvalidArgumentException('Call center hour must be HH:MM');
        }

        return $time;
    }
}
