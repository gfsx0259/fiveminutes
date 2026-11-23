<?php

declare(strict_types=1);

namespace App\Context\Lead;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeInterface;

#[Entity(repository: LiveDayInjectItemRepository::class, table: 'live_day_inject_items')]
class LiveDayInjectItem
{
    #[Column(type: 'bigPrimary')]
    public ?int $id = null;

    public function __construct(
        #[Column(type: 'uuid', name: 'organization_id')]
        public readonly string $organizationId,
        #[Column(type: 'uuid', name: 'order_id')]
        public readonly string $orderId,
        #[Column(type: 'uuid', name: 'preset_id')]
        public readonly string $presetId,
        #[Column(type: 'uuid', name: 'lead_uuid')]
        public readonly string $leadUuid,
        #[Column(type: 'datetime', name: 'due_at', typecast: 'datetime')]
        public readonly DateTimeInterface $dueAt,
        #[Column(type: 'datetime', name: 'created_at', typecast: 'datetime')]
        public readonly DateTimeInterface $createdAt,
        #[Column(type: 'string', typecast: LiveDayInjectStatus::class)]
        public LiveDayInjectStatus $status = LiveDayInjectStatus::Scheduled,
    ) {}
}