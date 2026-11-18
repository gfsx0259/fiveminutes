<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddOrganizationMarkup extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute('
            ALTER TABLE organizations
            ADD COLUMN markup_percent SMALLINT NOT NULL DEFAULT 10
        ');
        $this->database()->execute('
            ALTER TABLE organizations
            ADD CONSTRAINT organizations_markup_percent_chk
            CHECK (markup_percent >= 0 AND markup_percent <= 100)
        ');
    }

    public function down(): void
    {
        $this->database()->execute('ALTER TABLE organizations DROP CONSTRAINT IF EXISTS organizations_markup_percent_chk');
        $this->database()->execute('ALTER TABLE organizations DROP COLUMN IF EXISTS markup_percent');
    }
}