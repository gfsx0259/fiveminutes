<?php

declare(strict_types=1);

namespace App\Context\Lead;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: LeadConversionNoteRepository::class, table: 'lead_conversion_notes')]
class LeadConversionNote
{
    public function __construct(
        #[Column(type: 'uuid', name: 'lead_uuid', primary: true)]
        private readonly string $leadUuid,
        #[Column(type: 'string')]
        private readonly string $outcome,
        #[Column(type: 'text')]
        private readonly string $reason,
        #[Column(type: 'uuid', name: 'actor_id')]
        private readonly string $actorId,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        private readonly DateTimeInterface $createdAt,
    ) {}

    public function getLeadUuid(): string
    {
        return $this->leadUuid;
    }

    public function getOutcome(): string
    {
        return $this->outcome;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}