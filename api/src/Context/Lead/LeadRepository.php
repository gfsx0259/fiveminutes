<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\Market\Gender;
use App\Context\Market\Preset;
use App\Value\AgeBracket;
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
            'order_id' => $lead->getOrderId(),
            'email' => $lead->getEmail(),
            'phone' => $lead->getPhone(),
            'first_name' => $lead->getFirstName(),
            'last_name' => $lead->getLastName(),
            'country' => $lead->getCountry(),
            'traffic_source' => $lead->getTrafficSource(),
            'age' => $lead->getAge(),
            'gender' => $lead->getGender(),
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
            'SELECT uuid, organization_id, buyer_id, seller_id, ifNull(order_id, acceptance_id) AS order_id,
                    email, phone, first_name, last_name, country, traffic_source, age, gender,
                    ip, aff_sub, uniq_id, status, price_cents, created_at, updated_at, sold_at
             FROM leads FINAL
             WHERE ' . $where . '
             ORDER BY created_at DESC',
            $parameters,
        )->rows();

        return new ArrayCollection(array_map($this->lead(...), $rows));
    }

    /**
     * @return Collection<int, Lead>
     */
    public function findByOrganization(string $organizationId): Collection
    {
        return $this->select(
            'organization_id = :organization_id',
            ['organization_id' => $organizationId],
        );
    }

    public function countInStockForPreset(string $organizationId, Preset $preset, ?string $leadGender = null): int
    {
        $stock = $this->stockMatch($organizationId, $preset, $leadGender);
        $rows = $this->clickhouse->query(
            'SELECT count() AS available
             FROM leads FINAL
             WHERE ' . $stock['sql'],
            $stock['params'],
        )->rows();

        return (int) ($rows[0]['available'] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    public function countInStockByLeadGender(string $organizationId, Preset $preset): array
    {
        $stock = $this->stockMatch($organizationId, $preset);
        $rows = $this->clickhouse->query(
            'SELECT gender, count() AS available
             FROM leads FINAL
             WHERE ' . $stock['sql'] . '
             GROUP BY gender
             ORDER BY gender',
            $stock['params'],
        )->rows();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['gender']] = (int) ($row['available'] ?? 0);
        }

        return $counts;
    }

    /**
     * @return Collection<int, Lead>
     */
    public function findInStockForPreset(
        string $organizationId,
        Preset $preset,
        int $limit,
        ?string $leadGender = null,
    ): Collection {
        if ($limit < 1) {
            return new ArrayCollection();
        }

        $stock = $this->stockMatch($organizationId, $preset, $leadGender);
        $rows = $this->clickhouse->query(
            'SELECT uuid, organization_id, buyer_id, seller_id, ifNull(order_id, acceptance_id) AS order_id,
                    email, phone, first_name, last_name, country, traffic_source, age, gender,
                    ip, aff_sub, uniq_id, status, price_cents, created_at, updated_at, sold_at
             FROM leads FINAL
             WHERE ' . $stock['sql'] . '
             ORDER BY created_at DESC
             LIMIT ' . $limit,
            $stock['params'],
        )->rows();

        return new ArrayCollection(array_map($this->lead(...), $rows));
    }

    public function findByUuid(string $uuid): ?Lead
    {
        $rows = $this->clickhouse->query(
            'SELECT uuid, organization_id, buyer_id, seller_id, ifNull(order_id, acceptance_id) AS order_id,
                    email, phone, first_name, last_name, country, traffic_source, age, gender,
                    ip, aff_sub, uniq_id, status, price_cents, created_at, updated_at, sold_at
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
            $this->nullable($row['order_id'] ?? null),
            (string) $row['email'],
            (string) $row['phone'],
            (string) $row['first_name'],
            (string) $row['last_name'],
            (string) $row['country'],
            (string) ($row['traffic_source'] ?? ''),
            (string) ($row['age'] ?? ''),
            (string) ($row['gender'] ?? ''),
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

    private function genderFilter(): string
    {
        return '(:gender = \'' . Gender::All->value . '\' OR gender = :gender)';
    }

    private function trafficClause(): string
    {
        return 'lower(traffic_source) = lower(:traffic_source)';
    }

    /**
     * @return array{sql: string, params: array<string, int|string>}
     */
    private function stockMatch(string $organizationId, Preset $preset, ?string $leadGender = null): array
    {
        $age = $this->ageClause($preset);
        $sql = 'organization_id = :organization_id
               AND status = :status
               AND country = :country
               AND ' . $this->trafficClause() . '
               AND ' . $age['sql'] . '
               AND ' . $this->genderFilter();
        $params = [
            'organization_id' => $organizationId,
            'status' => LeadStatus::InStock->value,
            'country' => $preset->getCountry(),
            'traffic_source' => $preset->getTrafficSource(),
            'gender' => $preset->getGender(),
            ...$age['params'],
        ];
        if ($leadGender !== null) {
            $sql .= ' AND gender = :lead_gender';
            $params['lead_gender'] = $leadGender;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * @return array{sql: string, params: array<string, int|string>}
     */
    private function ageClause(Preset $preset): array
    {
        $bracket = trim($preset->getAge());
        if ($bracket === '') {
            return ['sql' => '1', 'params' => []];
        }
        $span = AgeBracket::span($bracket);
        if ($span === null) {
            return [
                'sql' => 'age = :preset_age',
                'params' => ['preset_age' => $bracket],
            ];
        }

        return [
            'sql' => '(age = :preset_age OR (match(age, \'^[0-9]+$\') AND toUInt16OrZero(age) >= :age_min AND toUInt16OrZero(age) <= :age_max))',
            'params' => [
                'preset_age' => $bracket,
                'age_min' => $span[0],
                'age_max' => $span[1],
            ],
        ];
    }
}
