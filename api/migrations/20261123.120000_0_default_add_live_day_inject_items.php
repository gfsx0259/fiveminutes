<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddLiveDayInjectItems extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            CREATE TABLE live_day_inject_items (
                id BIGSERIAL PRIMARY KEY,
                organization_id UUID NOT NULL,
                order_id UUID NOT NULL,
                preset_id UUID NOT NULL,
                lead_uuid UUID NOT NULL,
                due_at TIMESTAMPTZ NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute("
            ALTER TABLE live_day_inject_items
            ADD CONSTRAINT live_day_inject_items_status_chk
            CHECK (status IN ('scheduled', 'published', 'failed', 'cancelled', 'skipped'))
        ");
        $this->database()->execute('
            CREATE INDEX live_day_inject_items_due_scheduled_idx
            ON live_day_inject_items (due_at)
            WHERE status = \'scheduled\'
        ');
        $this->database()->execute('
            CREATE INDEX live_day_inject_items_order_id_idx
            ON live_day_inject_items (order_id)
        ');
        $this->database()->execute('
            CREATE UNIQUE INDEX live_day_inject_items_lead_scheduled_uidx
            ON live_day_inject_items (lead_uuid)
            WHERE status = \'scheduled\'
        ');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS live_day_inject_items');
    }
}