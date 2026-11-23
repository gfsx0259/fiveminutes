<?php

declare(strict_types=1);

namespace App\Context\Lead;

use Cycle\Database\DatabaseProviderInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LiveDayInjectStore
{
    public function __construct(private DatabaseProviderInterface $db) {}

    /**
     * @return list<string>
     */
    public function scheduledLeadUuids(string $organizationId): array
    {
        $rows = $this->db->database()->query(
            'SELECT lead_uuid::text AS lead_uuid
             FROM live_day_inject_items
             WHERE organization_id = ? AND status = ?',
            [$organizationId, LiveDayInjectStatus::Scheduled->value],
        )->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['lead_uuid'], $rows);
    }

    /**
     * @param list<string> $uuids
     * @return array<string, array{status: string, due_at: string, order_id: string}>
     */
    public function overlayByLeadUuids(array $uuids): array
    {
        if ($uuids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));
        $rows = $this->db->database()->query(
            "SELECT DISTINCT ON (lead_uuid)
                lead_uuid::text AS lead_uuid,
                status,
                due_at,
                order_id::text AS order_id
             FROM live_day_inject_items
             WHERE lead_uuid IN ({$placeholders})
             ORDER BY lead_uuid, id DESC",
            $uuids,
        )->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['lead_uuid']] = [
                'status' => (string) $row['status'],
                'due_at' => (string) $row['due_at'],
                'order_id' => (string) $row['order_id'],
            ];
        }

        return $map;
    }

    /**
     * @return array{scheduled: int, nextDueAt: ?string}
     */
    public function countsForOrder(string $orderId): array
    {
        $row = $this->db->database()->query(
            'SELECT count(*)::int AS scheduled, min(due_at) AS next_due
             FROM live_day_inject_items
             WHERE order_id = ? AND status = ?',
            [$orderId, LiveDayInjectStatus::Scheduled->value],
        )->fetch();

        $scheduled = (int) ($row['scheduled'] ?? 0);
        $next = $row['next_due'] ?? null;

        return [
            'scheduled' => $scheduled,
            'nextDueAt' => $next === null || trim((string) $next) === ''
                ? null
                : (new DateTimeImmutable((string) $next, new DateTimeZone('UTC')))->format(DATE_ATOM),
        ];
    }

    public function cancelScheduledForOrder(string $orderId): int
    {
        return $this->db->database()->execute(
            'UPDATE live_day_inject_items
             SET status = ?
             WHERE order_id = ? AND status = ?',
            [LiveDayInjectStatus::Cancelled->value, $orderId, LiveDayInjectStatus::Scheduled->value],
        );
    }

    /**
     * @return list<array{id: int, organization_id: string, order_id: string, lead_uuid: string}>
     */
    public function claimDue(int $limit): array
    {
        $limit = max(1, $limit);
        $rows = $this->db->database()->query(
            "WITH due AS (
                SELECT id
                FROM live_day_inject_items
                WHERE status = ? AND due_at <= NOW()
                ORDER BY due_at ASC
                LIMIT {$limit}
                FOR UPDATE SKIP LOCKED
            )
            UPDATE live_day_inject_items i
            SET status = ?
            FROM due
            WHERE i.id = due.id
            RETURNING i.id, i.organization_id::text, i.order_id::text, i.lead_uuid::text",
            [LiveDayInjectStatus::Scheduled->value, LiveDayInjectStatus::Published->value],
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'organization_id' => (string) $row['organization_id'],
            'order_id' => (string) $row['order_id'],
            'lead_uuid' => (string) $row['lead_uuid'],
        ], $rows);
    }

    public function markFailed(int $id): void
    {
        $this->db->database()->execute(
            'UPDATE live_day_inject_items SET status = ? WHERE id = ?',
            [LiveDayInjectStatus::Failed->value, $id],
        );
    }

    public function markSkipped(int $id): void
    {
        $this->db->database()->execute(
            'UPDATE live_day_inject_items SET status = ? WHERE id = ?',
            [LiveDayInjectStatus::Skipped->value, $id],
        );
    }
}