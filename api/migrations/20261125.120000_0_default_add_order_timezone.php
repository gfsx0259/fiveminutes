<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddOrderTimezone extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            ALTER TABLE orders
            ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'UTC'
        ");
    }

    public function down(): void
    {
        $this->database()->execute('
            ALTER TABLE orders
            DROP COLUMN IF EXISTS timezone
        ');
    }
}
