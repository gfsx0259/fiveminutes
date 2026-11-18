<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class SplitPresetPrices extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute('ALTER TABLE presets DROP COLUMN base_price_cents');
        $this->database()->execute('
            ALTER TABLE presets
                ADD COLUMN cpl_price_cents INTEGER NOT NULL CHECK (cpl_price_cents > 0),
                ADD COLUMN cpa_price_cents INTEGER NOT NULL CHECK (cpa_price_cents > 0)
        ');
        $this->database()->execute("
            ALTER TABLE orders
                ADD COLUMN payment_model VARCHAR(8) NOT NULL CHECK (payment_model IN ('cpl', 'cpa'))
        ");
    }

    public function down(): void
    {
        $this->database()->execute('ALTER TABLE orders DROP COLUMN IF EXISTS payment_model');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS cpl_price_cents');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS cpa_price_cents');
        $this->database()->execute('
            ALTER TABLE presets
                ADD COLUMN base_price_cents INTEGER NOT NULL DEFAULT 1 CHECK (base_price_cents > 0)
        ');
        $this->database()->execute('ALTER TABLE presets ALTER COLUMN base_price_cents DROP DEFAULT');
    }
}
