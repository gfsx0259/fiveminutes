<?php

declare(strict_types=1);

namespace App\Context\Market;

use App\Value\Criterion;
use App\Value\Geo;
use App\Value\Money;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use InvalidArgumentException;
use ValueError;

#[Entity(repository: PresetRepository::class, table: 'presets')]
class Preset
{
    #[Column(type: 'string')]
    private readonly string $country;

    #[Column(type: 'string', name: 'traffic_source')]
    private readonly string $trafficSource;

    #[Column(type: 'string')]
    private readonly string $age;

    #[Column(type: 'string')]
    private readonly string $gender;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'admin_id')]
        private readonly string $adminId,
        string $country,
        string $trafficSource,
        string $age,
        string $gender,
        #[Column(type: 'integer', typecast: 'int')]
        private readonly int $cr,
        #[Column(type: 'integer', name: 'cpl_price_cents', typecast: 'int')]
        private readonly int $cplPriceCents,
        #[Column(type: 'integer', name: 'cpa_price_cents', typecast: 'int')]
        private readonly int $cpaPriceCents,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {
        if ($this->cr < 0 || $this->cr > 100) {
            throw new InvalidArgumentException('CR must be from 0 to 100');
        }
        new Money($this->cplPriceCents);
        new Money($this->cpaPriceCents);
        $this->country = (new Geo($country))->toString();
        if (preg_match('/^[A-Z]{2}$/', $this->country) !== 1) {
            throw new InvalidArgumentException('Country must be an ISO code, like DE');
        }
        try {
            $this->trafficSource = TrafficSource::from((new Criterion($trafficSource, 'Traffic source'))->toString())->value;
        } catch (ValueError) {
            throw new InvalidArgumentException('Traffic source must be facebook, google or tiktok');
        }
        $age = trim($age);
        if ($age === '') {
            $this->age = '';
        } elseif (strlen($age) > 64) {
            throw new InvalidArgumentException('Age must be 64 characters or fewer');
        } else {
            $this->age = $age;
        }
        try {
            $this->gender = Gender::from((new Criterion($gender, 'Gender'))->toString())->value;
        } catch (ValueError) {
            throw new InvalidArgumentException('Gender must be male, female or all');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getAdminId(): string
    {
        return $this->adminId;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getTrafficSource(): string
    {
        return $this->trafficSource;
    }

    public function getAge(): string
    {
        return $this->age;
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function getCr(): int
    {
        return $this->cr;
    }

    public function getCplPriceCents(): int
    {
        return $this->cplPriceCents;
    }

    public function getCpaPriceCents(): int
    {
        return $this->cpaPriceCents;
    }

    public function priceFor(PaymentModel $model): int
    {
        return match ($model) {
            PaymentModel::Cpl => $this->cplPriceCents,
            PaymentModel::Cpa => $this->cpaPriceCents,
        };
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function accepts(string $country, string $trafficSource, string $age, string $gender): bool
    {
        return $this->country === $country
            && $this->trafficSource === $trafficSource
            && $this->age === $age
            && ($this->gender === $gender || $this->gender === Gender::All->value);
    }
}
