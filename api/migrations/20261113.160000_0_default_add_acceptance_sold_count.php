<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddAcceptanceSoldCount extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute('
            ALTER TABLE acceptances
                ADD COLUMN sold_count INTEGER NOT NULL DEFAULT 0,
                ADD CONSTRAINT acceptances_sold_count_chk CHECK (sold_count >= 0 AND sold_count <= volume)
        ');
        $this->database()->execute('
            CREATE TABLE lead_placements (
                lead_uuid UUID PRIMARY KEY,
                acceptance_id UUID NULL REFERENCES acceptances (id),
                seller_id UUID NULL REFERENCES users (id),
                price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
                sold_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL,
                CHECK (
                    (
                        acceptance_id IS NULL
                        AND seller_id IS NULL
                        AND sold_at IS NULL
                        AND price_cents = 0
                    )
                    OR (
                        acceptance_id IS NOT NULL
                        AND seller_id IS NOT NULL
                        AND sold_at IS NOT NULL
                        AND price_cents > 0
                    )
                )
            )
        ');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS lead_placements');
        $this->database()->execute('ALTER TABLE acceptances DROP CONSTRAINT IF EXISTS acceptances_sold_count_chk');
        $this->database()->execute('ALTER TABLE acceptances DROP COLUMN IF EXISTS sold_count');
    }
}
