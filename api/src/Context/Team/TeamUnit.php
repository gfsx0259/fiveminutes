<?php

declare(strict_types=1);

namespace App\Context\Team;

use App\Clock;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;
use InvalidArgumentException;

#[Entity(repository: TeamUnitRepository::class, table: 'team_units')]
class TeamUnit
{
    #[Column(type: 'string')]
    private string $name;

    #[Column(type: 'string', name: 'budget_period')]
    private string $budgetPeriod;

    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'organization_id')]
        private readonly string $organizationId,
        #[Column(type: 'uuid', name: 'account_id')]
        private readonly string $accountId,
        #[Column(type: 'uuid', name: 'parent_id', nullable: true)]
        private ?string $parentId,
        string $name,
        #[Column(type: 'integer', name: 'budget_cents', nullable: true, typecast: 'int')]
        private ?int $budgetCents,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
        #[Column(type: 'datetime', name: 'updated_at', typecast: 'datetime')]
        private DateTimeInterface $updatedAt,
    ) {
        $this->name = $this->requireName($name);
        $this->budgetPeriod = BudgetPeriod::Total->value;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBudgetCents(): ?int
    {
        return $this->budgetCents;
    }

    public function getBudgetPeriod(): BudgetPeriod
    {
        return BudgetPeriod::from($this->budgetPeriod);
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function rename(string $name, Clock $clock): void
    {
        $this->name = $this->requireName($name);
        $this->updatedAt = $clock->now();
    }

    public function moveTo(?string $parentId, Clock $clock): void
    {
        $this->parentId = $parentId;
        $this->updatedAt = $clock->now();
    }

    public function limitBudget(?int $budgetCents, BudgetPeriod $period, Clock $clock): void
    {
        if ($budgetCents !== null && $budgetCents < 0) {
            throw new InvalidArgumentException('Budget cannot be negative');
        }

        $this->budgetCents = $budgetCents;
        $this->budgetPeriod = $period->value;
        $this->updatedAt = $clock->now();
    }

    private function requireName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new InvalidArgumentException('Team name must be 1 to 80 characters');
        }

        return $name;
    }
}
