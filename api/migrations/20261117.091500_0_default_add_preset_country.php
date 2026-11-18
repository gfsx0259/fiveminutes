<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddPresetCountry extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            ALTER TABLE presets
                ADD COLUMN country VARCHAR(2) NOT NULL CHECK (country ~ '^[A-Z]{2}$')
        ");
        $this->database()->execute('
            ALTER TABLE presets
                DROP CONSTRAINT presets_organization_id_traffic_source_age_gender_key
        ');
        $this->database()->execute('
            ALTER TABLE presets
                ADD CONSTRAINT presets_organization_id_country_traffic_source_age_gender_key
                UNIQUE (organization_id, country, traffic_source, age, gender)
        ');
    }

    public function down(): void
    {
        $this->database()->execute('
            ALTER TABLE presets
                DROP CONSTRAINT IF EXISTS presets_organization_id_country_traffic_source_age_gender_key
        ');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS country');
        $this->database()->execute('
            ALTER TABLE presets
                ADD CONSTRAINT presets_organization_id_traffic_source_age_gender_key
                UNIQUE (organization_id, traffic_source, age, gender)
        ');
    }
}
