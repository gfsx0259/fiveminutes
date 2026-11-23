<?php

declare(strict_types=1);

namespace App\Migration;

use App\Context\Market\ShelfAgePolicy;
use Cycle\Migrations\Migration;

class AddOrganizationShelfAgePolicy extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $default = json_encode(ShelfAgePolicy::default()->toArray(), JSON_THROW_ON_ERROR);
        $this->database()->execute("
            ALTER TABLE organizations
            ADD COLUMN shelf_age_policy JSONB NOT NULL DEFAULT '" . $default . "'::jsonb
        ");
    }

    public function down(): void
    {
        $this->database()->execute('ALTER TABLE organizations DROP COLUMN IF EXISTS shelf_age_policy');
    }
}