<?php

declare(strict_types=1);

namespace App\Context\Team;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: BudgetAlertRepository::class, table: 'team_budget_alerts')]
class BudgetAlert
{
    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'unit_id')]
        private readonly string $unitId,
        #[Column(type: 'datetime', name: 'period_start', typecast: 'datetime')]
        private readonly DateTimeInterface $periodStart,
        #[Column(type: 'smallInteger', typecast: 'int')]
        private readonly int $threshold,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getUnitId(): string
    {
        return $this->unitId;
    }

    public function getPeriodStart(): DateTimeInterface
    {
        return $this->periodStart;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
