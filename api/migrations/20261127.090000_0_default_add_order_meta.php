<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddOrderMeta extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            ALTER TABLE orders
            ADD COLUMN meta JSONB NOT NULL DEFAULT '{}'::jsonb
        ");
    }

    public function down(): void
    {
        $this->database()->execute('
            ALTER TABLE orders
            DROP COLUMN IF EXISTS meta
        ');
    }
}
