<?php

declare(strict_types=1);

namespace App\Migration;

use App\Context\Market\RotationAxes;
use Cycle\Migrations\Migration;

class AddOrganizationRotationAxes extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $default = json_encode(RotationAxes::default()->toList(), JSON_THROW_ON_ERROR);
        $this->database()->execute("
            ALTER TABLE organizations
            ADD COLUMN rotation_axes JSONB NOT NULL DEFAULT '" . $default . "'::jsonb
        ");
    }

    public function down(): void
    {
        $this->database()->execute('ALTER TABLE organizations DROP COLUMN IF EXISTS rotation_axes');
    }
}
