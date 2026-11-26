<?php

declare(strict_types=1);

namespace App\Context\Team;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: TeamEventRepository::class, table: 'team_events')]
class TeamEvent
{
    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        #[Column(type: 'uuid', primary: true)]
        private readonly string $id,
        #[Column(type: 'uuid', name: 'account_id')]
        private readonly string $accountId,
        #[Column(type: 'uuid', name: 'unit_id', nullable: true)]
        private readonly ?string $unitId,
        #[Column(type: 'string')]
        private readonly string $actor,
        #[Column(type: 'string')]
        private readonly string $action,
        #[Column(type: 'jsonb', typecast: 'json')]
        private readonly array $details,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function getActor(): string
    {
        return $this->actor;
    }

    public function getAction(): TeamAction
    {
        return TeamAction::from($this->action);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
