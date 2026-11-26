<?php

declare(strict_types=1);

namespace App\Context\Team;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: BudgetChangeRepository::class, table: 'team_budget_changes')]
class BudgetChange
{
    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'unit_id')]
        private readonly string $unitId,
        #[Column(type: 'integer', name: 'budget_cents', nullable: true, typecast: 'int')]
        private readonly ?int $budgetCents,
        #[Column(type: 'string', name: 'budget_period')]
        private readonly string $budgetPeriod,
        #[Column(type: 'string', name: 'changed_by')]
        private readonly string $changedBy,
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

    public function getBudgetCents(): ?int
    {
        return $this->budgetCents;
    }

    public function getBudgetPeriod(): BudgetPeriod
    {
        return BudgetPeriod::from($this->budgetPeriod);
    }

    public function getChangedBy(): string
    {
        return $this->changedBy;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
