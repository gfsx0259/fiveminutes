<?php

declare(strict_types=1);

namespace App\Context\Lead;

use Beeterty\ClickHouse\Client;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final readonly class LeadRepository
{
    public function __construct(private Client $clickhouse) {}

    public function add(Lead $lead): void
    {
        $this->clickhouse->insert('leads', [[
            'uuid' => $lead->getUuid(),
            'organization_id' => $lead->getOrganizationId(),
            'buyer_id' => $lead->getBuyerId(),
            'seller_id' => $lead->getSellerId(),
            'acceptance_id' => $lead->getAcceptanceId(),
            'email' => $lead->getEmail(),
            'phone' => $lead->getPhone(),
            'first_name' => $lead->getFirstName(),
            'last_name' => $lead->getLastName(),
            'country' => $lead->getCountry(),
            'ip' => $lead->getIp(),
            'aff_sub' => $lead->getAffSub(),
            'uniq_id' => $lead->getUniqId(),
            'status' => $lead->getStatus()->value,
            'price_cents' => $lead->getPriceCents(),
            'created_at' => $this->format($lead->getCreatedAt()),
            'updated_at' => $this->format($lead->getUpdatedAt()),
            'sold_at' => $lead->getSoldAt() === null ? null : $this->format($lead->getSoldAt()),
        ]]);
    }

    /**
     * @return Collection<int, Lead>
     */
    public function findMined(string $organizationId, string $buyerId): Collection
    {
        return $this->select(
            'organization_id = :organization_id AND buyer_id = :buyer_id',
            ['organization_id' => $organizationId, 'buyer_id' => $buyerId],
        );
    }

    /**
     * @return Collection<int, Lead>
     */
    public function findPurchased(string $organizationId, string $sellerId): Collection
    {
        return $this->select(
            'organization_id = :organization_id AND seller_id = :seller_id',
            ['organization_id' => $organizationId, 'seller_id' => $sellerId],
        );
    }

    /**
     * @param array<string, string> $parameters
     * @return Collection<int, Lead>
     */
    private function select(string $where, array $parameters): Collection
    {
        $rows = $this->clickhouse->query(
            'SELECT uuid, organization_id, buyer_id, seller_id, acceptance_id, email, phone,
                    first_name, last_name, country, ip, aff_sub, uniq_id, status, price_cents,
                    created_at, updated_at, sold_at
             FROM leads FINAL
             WHERE ' . $where . '
             ORDER BY created_at DESC',
            $parameters,
        )->rows();

        return new ArrayCollection(array_map($this->lead(...), $rows));
    }

    public function findByUuid(string $uuid): ?Lead
    {
        $rows = $this->clickhouse->query(
            'SELECT uuid, organization_id, buyer_id, seller_id, acceptance_id, email, phone,
                    first_name, last_name, country, ip, aff_sub, uniq_id, status, price_cents,
                    created_at, updated_at, sold_at
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
            (string) $row['buyer_id'],
            $this->nullable($row['seller_id'] ?? null),
            $this->nullable($row['acceptance_id'] ?? null),
            (string) $row['email'],
            (string) $row['phone'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['country'],
            (string) $row['ip'],
            (string) $row['aff_sub'],
            (string) $row['uniq_id'],
            LeadStatus::from((string) $row['status']),
            (int) $row['price_cents'],
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
            $this->instant($row['sold_at'] ?? null),
        );
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        $value = $this->nullable($value);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function format(DateTimeInterface $at): string
    {
        return $at->format('Y-m-d H:i:s.u');
    }
}
