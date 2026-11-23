<?php

declare(strict_types=1);

namespace App\Context\Lead;

use App\Context\Market\Gender;
use App\Context\Market\Preset;
use App\Context\Market\ShelfAgePolicy;
use App\Context\Market\ShelfAgePolicyQuery;
use App\Context\Market\ShelfAgeZone;
use App\Value\AgeBracket;
use Beeterty\ClickHouse\Client;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final readonly class LeadRepository
{
    private const string SELECT_COLUMNS = 'uuid, organization_id, buyer_id, seller_id, ifNull(order_id, acceptance_id) AS order_id,
                    email, phone, first_name, last_name, country, traffic_source, age, gender,
                    ip, aff_sub, uniq_id, status, price_cents, created_at, updated_at, sold_at,
                    is_delivered, delivery_error, client_status, client_deposit';

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
            'is_delivered' => $lead->isDelivered() ? 1 : 0,
            'delivery_error' => $lead->hasDeliveryError() ? 1 : 0,
            'client_status' => $lead->getClientStatus(),
            'client_deposit' => $lead->hasClientDeposit() ? 1 : 0,
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
            'SELECT ' . self::SELECT_COLUMNS . '
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

    /**
     * Latest in-stock leads (for dev shelf-age backdating).
     *
     * @return list<Lead>
     */
    public function findLatestInStock(int $limit, ?string $organizationId = null, ?string $country = null): array
    {
        if ($limit < 1) {
            return [];
        }
        $sql = 'status = :status';
        $params = ['status' => LeadStatus::InStock->value];
        if ($organizationId !== null && $organizationId !== '') {
            $sql .= ' AND organization_id = :organization_id';
            $params['organization_id'] = $organizationId;
        }
        if ($country !== null && $country !== '') {
            $sql .= ' AND country = :country';
            $params['country'] = $country;
        }
        $rows = $this->clickhouse->query(
            'SELECT ' . self::SELECT_COLUMNS . '
             FROM leads FINAL
             WHERE ' . $sql . '
             ORDER BY created_at DESC
             LIMIT ' . $limit,
            $params,
        )->rows();

        return array_map($this->lead(...), $rows);
    }

    public function latestStockAtForPreset(string $organizationId, Preset $preset): ?DateTimeImmutable
    {
        $stock = $this->stockMatch($organizationId, $preset);
        $rows = $this->clickhouse->query(
            'SELECT max(created_at) AS latest
             FROM leads FINAL
             WHERE ' . $stock['sql'],
            $stock['params'],
        )->rows();
        $latest = $rows[0]['latest'] ?? null;
        if ($latest === null || trim((string) $latest) === '') {
            return null;
        }

        $at = new DateTimeImmutable((string) $latest);
        if ((int) $at->format('Y') < 2000) {
            return null;
        }

        return $at;
    }

    public function countSellableInStockForPreset(
        string $organizationId,
        Preset $preset,
        ShelfAgePolicy $policy,
        ?string $leadGender = null,
    ): int {
        $stock = $this->sellableStockMatch($organizationId, $preset, $policy, $leadGender);
        $rows = $this->clickhouse->query(
            'SELECT count() AS available
             FROM leads FINAL
             WHERE ' . $stock['sql'],
            $stock['params'],
        )->rows();

        return (int) ($rows[0]['available'] ?? 0);
    }

    public function countLiveInStockForPreset(
        string $organizationId,
        Preset $preset,
        ShelfAgePolicy $policy,
        ?string $leadGender = null,
        array $excludeLeadUuids = [],
    ): int {
        $match = $this->liveStockMatch($organizationId, $preset, $policy, $leadGender, $excludeLeadUuids);
        $rows = $this->clickhouse->query(
            'SELECT count() AS live_count
             FROM leads FINAL
             WHERE ' . $match['sql'],
            $match['params'],
        )->rows();

        return (int) ($rows[0]['live_count'] ?? 0);
    }

    /**
     * @param list<string> $excludeLeadUuids
     * @return list<Lead>
     */
    public function findLiveInStockForPreset(
        string $organizationId,
        Preset $preset,
        ShelfAgePolicy $policy,
        int $limit,
        array $excludeLeadUuids = [],
        ?string $leadGender = null,
    ): array {
        if ($limit < 1) {
            return [];
        }
        $match = $this->liveStockMatch($organizationId, $preset, $policy, $leadGender, $excludeLeadUuids);
        $rows = $this->clickhouse->query(
            'SELECT ' . self::SELECT_COLUMNS . '
             FROM leads FINAL
             WHERE ' . $match['sql'] . '
             ORDER BY created_at ASC
             LIMIT ' . $limit,
            $match['params'],
        )->rows();

        return array_map($this->lead(...), $rows);
    }

    /**
     * Sellable shelf mix (live zone excluded).
     *
     * @return array{fresh: int, good: int, expired: int}
     */
    public function stockShelfMixForPreset(string $organizationId, Preset $preset, ShelfAgePolicy $policy): array
    {
        $stock = $this->sellableStockMatch($organizationId, $preset, $policy);
        $query = new ShelfAgePolicyQuery($policy);
        $parts = [];
        foreach ($policy->sellableZones() as $zone) {
            $alias = $zone->id;
            $parts[] = $query->countIfForZone($zone) . ' AS ' . $alias;
        }
        $rows = $this->clickhouse->query(
            'SELECT ' . implode(', ', $parts) . '
             FROM leads FINAL
             WHERE ' . $stock['sql'],
            $stock['params'],
        )->rows();
        $row = $rows[0] ?? [];

        return [
            ShelfAgeZone::FRESH => (int) ($row[ShelfAgeZone::FRESH] ?? 0),
            ShelfAgeZone::GOOD => (int) ($row[ShelfAgeZone::GOOD] ?? 0),
            ShelfAgeZone::EXPIRED => (int) ($row[ShelfAgeZone::EXPIRED] ?? 0),
        ];
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
     * @return list<Lead>
     */
    public function findSellableStockForPreset(
        string $organizationId,
        Preset $preset,
        int $limit,
        ShelfAgePolicy $policy,
        ?string $leadGender = null,
    ): array {
        if ($limit < 1) {
            return [];
        }

        $stock = $this->sellableStockMatch($organizationId, $preset, $policy, $leadGender);
        $order = (new ShelfAgePolicyQuery($policy))->pickOrderSql();
        $rows = $this->clickhouse->query(
            'SELECT ' . self::SELECT_COLUMNS . '
             FROM leads FINAL
             WHERE ' . $stock['sql'] . '
             ORDER BY ' . $order . '
             LIMIT ' . $limit,
            $stock['params'],
        )->rows();

        return array_map($this->lead(...), $rows);
    }

    public function findByUuid(string $uuid): ?Lead
    {
        $rows = $this->clickhouse->query(
            'SELECT ' . self::SELECT_COLUMNS . '
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
            $this->bool($row['is_delivered'] ?? 0),
            $this->bool($row['delivery_error'] ?? 0),
            (string) ($row['client_status'] ?? ''),
            $this->bool($row['client_deposit'] ?? 0),
        );
    }

    private function bool(mixed $value): bool
    {
        return (int) $value === 1;
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
    private function sellableStockMatch(
        string $organizationId,
        Preset $preset,
        ShelfAgePolicy $policy,
        ?string $leadGender = null,
    ): array {
        $stock = $this->stockMatch($organizationId, $preset, $leadGender);
        $predicate = (new ShelfAgePolicyQuery($policy))->sellableAgePredicate();
        $stock['sql'] .= ' AND ' . $predicate;

        return $stock;
    }

    /**
     * @param list<string> $excludeLeadUuids
     * @return array{sql: string, params: array<string, int|string>}
     */
    private function liveStockMatch(
        string $organizationId,
        Preset $preset,
        ShelfAgePolicy $policy,
        ?string $leadGender,
        array $excludeLeadUuids,
    ): array {
        $live = $policy->liveZone();
        $age = (new ShelfAgePolicyQuery($policy))->ageMinutesExpression();
        $stock = $this->stockMatch($organizationId, $preset, $leadGender);
        $liveSql = $age . ' >= ' . $live->startMinutes;
        if ($live->endMinutes !== null) {
            $liveSql .= ' AND ' . $age . ' < ' . $live->endMinutes;
        }
        $stock['sql'] .= ' AND ' . $liveSql;
        if ($excludeLeadUuids !== []) {
            $parts = [];
            foreach (array_values($excludeLeadUuids) as $index => $uuid) {
                $key = 'exclude_uuid_' . $index;
                $stock['params'][$key] = $uuid;
                $parts[] = 'uuid != :' . $key;
            }
            $stock['sql'] .= ' AND ' . implode(' AND ', $parts);
        }

        return $stock;
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
