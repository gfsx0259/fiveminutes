<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddDemandCallCenter extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute('
            ALTER TABLE demands
                ADD COLUMN service_on DATE NOT NULL,
                ADD COLUMN opens_at TIME NOT NULL,
                ADD COLUMN closes_at TIME NOT NULL,
                ADD CONSTRAINT demands_call_center_hours_chk CHECK (opens_at < closes_at)
        ');
    }

    public function down(): void
    {
        $this->database()->execute('ALTER TABLE demands DROP CONSTRAINT IF EXISTS demands_call_center_hours_chk');
        $this->database()->execute('ALTER TABLE demands DROP COLUMN IF EXISTS closes_at');
        $this->database()->execute('ALTER TABLE demands DROP COLUMN IF EXISTS opens_at');
        $this->database()->execute('ALTER TABLE demands DROP COLUMN IF EXISTS service_on');
    }
}
