<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddOrderStockPick extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            ALTER TABLE orders
            ADD COLUMN stock_pick_priority VARCHAR(32),
            ADD COLUMN stock_pick_zone VARCHAR(16)
        ");
        $this->database()->execute("
            UPDATE orders
            SET stock_pick_priority = 'oldest_first'
            WHERE stock AND stock_pick_priority IS NULL
        ");
    }

    public function down(): void
    {
        $this->database()->execute('
            ALTER TABLE orders
            DROP COLUMN IF EXISTS stock_pick_priority,
            DROP COLUMN IF EXISTS stock_pick_zone
        ');
    }
}