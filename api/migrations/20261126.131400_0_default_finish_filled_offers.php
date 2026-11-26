<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class FinishFilledOffers extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            UPDATE offers
            SET status = 'finished', updated_at = NOW()
            WHERE status = 'approved' AND supplied_count >= volume
        ");
    }

    public function down(): void
    {
        $this->database()->execute("
            UPDATE offers
            SET status = 'approved'
            WHERE status = 'finished'
        ");
    }
}
