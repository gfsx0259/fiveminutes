<?php

declare(strict_types=1);

namespace App\Context\Market;

use Cycle\Database\DatabaseProviderInterface;

final readonly class OrderCost
{
    public function __construct(private DatabaseProviderInterface $db) {}

    public function soldTotalCents(string $orderId): int
    {
        $row = $this->db->database()->query(
            'SELECT coalesce(sum(price_cents), 0)::int AS total
             FROM lead_placements
             WHERE order_id = ? AND sold_at IS NOT NULL',
            [$orderId],
        )->fetch();

        return (int) ($row['total'] ?? 0);
    }
}