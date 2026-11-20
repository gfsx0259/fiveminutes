<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class PresetSingleBasePrice extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute('
            UPDATE presets
            SET floor_price_cents = GREATEST(floor_price_cents, stock_price_cents)
        ');
        $this->database()->execute('
            ALTER TABLE presets
                RENAME COLUMN floor_price_cents TO base_price_cents
        ');
        $this->database()->execute('
            ALTER TABLE presets DROP CONSTRAINT IF EXISTS presets_floor_price_cents_check
        ');
        $this->database()->execute('
            ALTER TABLE presets DROP CONSTRAINT IF EXISTS presets_stock_price_cents_check
        ');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN stock_price_cents');
        $this->database()->execute('
            ALTER TABLE presets
                ADD CONSTRAINT presets_base_price_cents_check CHECK (base_price_cents > 0)
        ');
    }

    public function down(): void
    {
        $this->database()->execute('
            ALTER TABLE presets
                ADD COLUMN stock_price_cents INTEGER
        ');
        $this->database()->execute('
            UPDATE presets SET stock_price_cents = base_price_cents
        ');
        $this->database()->execute('
            ALTER TABLE presets
                ALTER COLUMN stock_price_cents SET NOT NULL,
                ADD CONSTRAINT presets_stock_price_cents_check CHECK (stock_price_cents > 0)
        ');
        $this->database()->execute('
            ALTER TABLE presets DROP CONSTRAINT IF EXISTS presets_base_price_cents_check
        ');
        $this->database()->execute('
            ALTER TABLE presets
                RENAME COLUMN base_price_cents TO floor_price_cents
        ');
        $this->database()->execute('
            ALTER TABLE presets
                ADD CONSTRAINT presets_floor_price_cents_check CHECK (floor_price_cents > 0)
        ');
    }
}