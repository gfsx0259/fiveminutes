<?php

declare(strict_types=1);

namespace App\Context\Lead;

use Beeterty\ClickHouse\Client;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class LeadRepository
{
    public function __construct(private Client $clickhouse) {}

    public function add(Lead $lead): void
    {
        $this->clickhouse->insert('leads', [[
            'uuid' => $lead->getUuid(),
            'organization_id' => $lead->getOrganizationId(),
            'acceptance_id' => $lead->getAcceptanceId(),
            'email' => $lead->getEmail(),
            'phone' => $lead->getPhone(),
            'uniq_id' => $lead->getUniqId(),
            'status' => $lead->getStatus()->value,
            'price_cents' => $lead->getPriceCents(),
            'created_at' => $this->format($lead->getCreatedAt()),
            'updated_at' => $this->format($lead->getUpdatedAt()),
        ]]);
    }

    /**
     * @return Collection<int, Lead>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        $rows = $this->clickhouse->query(
            'SELECT uuid, organization_id, acceptance_id, email, phone, uniq_id, status, price_cents, created_at, updated_at
             FROM leads FINAL
             WHERE organization_id = :organization_id',
            ['organization_id' => $organizationId],
        )->rows();

        return new ArrayCollection(array_map($this->lead(...), $rows));
    }

    public function findByUuid(string $uuid): ?Lead
    {
        $rows = $this->clickhouse->query(
            'SELECT uuid, organization_id, acceptance_id, email, phone, uniq_id, status, price_cents, created_at, updated_at
             FROM leads FINAL
             WHERE uuid = :uuid',
            ['uuid' => $uuid],
        )->rows();

        $row = $rows[0] ?? null;

        return $row === null ? null : $this->lead($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lead(array $row): Lead
    {
        return new Lead(
            (string) $row['uuid'],
            (string) $row['organization_id'],
            (string) $row['acceptance_id'],
            (string) $row['email'],
            (string) $row['phone'],
            (string) $row['uniq_id'],
            LeadStatus::from((string) $row['status']),
            (int) $row['price_cents'],
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private function format(\DateTimeInterface $at): string
    {
        return $at->format('Y-m-d H:i:s.u');
    }
}
