<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

/**
 * New enum value only. Postgres cannot use a value added in the same transaction,
 * so the backfill of already full offers lives in the next migration.
 */
class AddOfferFinishedStatus extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("ALTER TYPE offer_status ADD VALUE IF NOT EXISTS 'finished'");
    }

    public function down(): void
    {
        // Postgres has no way to drop a single enum value.
    }
}
