<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class PartnerPresetCatalog extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            ALTER TABLE presets
                ADD COLUMN vertical VARCHAR(16) NOT NULL DEFAULT 'other'
                    CHECK (vertical IN (
                        'gambling', 'betting', 'crypto', 'forex', 'dating', 'nutra', 'finance', 'other'
                    )),
                ADD COLUMN payment_model VARCHAR(8),
                ADD COLUMN floor_price_cents INTEGER,
                ADD COLUMN stock_price_cents INTEGER
        ");
        $this->database()->execute('ALTER TABLE presets ALTER COLUMN vertical DROP DEFAULT');

        $this->database()->execute("
            UPDATE presets
            SET payment_model = 'cpl',
                floor_price_cents = cpl_price_cents,
                stock_price_cents = cpl_price_cents
        ");

        $this->database()->execute('
            ALTER TABLE presets
                DROP CONSTRAINT IF EXISTS presets_organization_id_country_traffic_source_age_gender_key
        ');

        $this->database()->execute("
            INSERT INTO presets (
                id, organization_id, admin_id, country, traffic_source, age, gender, cr, created_at,
                cpl_price_cents, cpa_price_cents,
                vertical, payment_model, floor_price_cents, stock_price_cents
            )
            SELECT
                gen_random_uuid(),
                organization_id,
                admin_id,
                country,
                traffic_source,
                age,
                gender,
                cr,
                created_at,
                cpl_price_cents,
                cpa_price_cents,
                vertical,
                'cpa',
                cpa_price_cents,
                cpa_price_cents
            FROM presets
            WHERE payment_model = 'cpl'
        ");

        $this->database()->execute("
            UPDATE orders o
            SET preset_id = cpa.id
            FROM presets cpl
            JOIN presets cpa
                ON cpa.organization_id = cpl.organization_id
                AND cpa.country = cpl.country
                AND cpa.traffic_source = cpl.traffic_source
                AND cpa.age = cpl.age
                AND cpa.gender = cpl.gender
                AND cpa.vertical = cpl.vertical
                AND cpa.cr = cpl.cr
                AND cpa.payment_model = 'cpa'
            WHERE o.preset_id = cpl.id
                AND cpl.payment_model = 'cpl'
                AND o.payment_model = 'cpa'
        ");

        $this->database()->execute("
            ALTER TABLE presets
                ALTER COLUMN payment_model SET NOT NULL,
                ADD CONSTRAINT presets_payment_model_check
                    CHECK (payment_model IN ('cpl', 'cpa')),
                ALTER COLUMN floor_price_cents SET NOT NULL,
                ADD CONSTRAINT presets_floor_price_cents_check CHECK (floor_price_cents > 0),
                ALTER COLUMN stock_price_cents SET NOT NULL,
                ADD CONSTRAINT presets_stock_price_cents_check CHECK (stock_price_cents > 0)
        ");

        $this->database()->execute('
            ALTER TABLE presets
                ADD CONSTRAINT presets_organization_segment_key
                UNIQUE (organization_id, country, traffic_source, age, gender, vertical, payment_model)
        ');

        $this->database()->execute('ALTER TABLE presets DROP COLUMN cpl_price_cents');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN cpa_price_cents');
    }

    public function down(): void
    {
        $this->database()->execute('
            ALTER TABLE presets
                ADD COLUMN cpl_price_cents INTEGER,
                ADD COLUMN cpa_price_cents INTEGER
        ');
        $this->database()->execute("
            UPDATE presets
            SET cpl_price_cents = floor_price_cents,
                cpa_price_cents = floor_price_cents
            WHERE payment_model = 'cpl'
        ");
        $this->database()->execute('DELETE FROM presets WHERE payment_model = \'cpa\'');
        $this->database()->execute('
            ALTER TABLE presets
                ALTER COLUMN cpl_price_cents SET NOT NULL,
                ALTER COLUMN cpa_price_cents SET NOT NULL
        ');

        $this->database()->execute('
            ALTER TABLE presets DROP CONSTRAINT IF EXISTS presets_organization_segment_key
        ');
        $this->database()->execute('
            ALTER TABLE presets
                ADD CONSTRAINT presets_organization_id_country_traffic_source_age_gender_key
                UNIQUE (organization_id, country, traffic_source, age, gender)
        ');

        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS vertical');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS payment_model');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS floor_price_cents');
        $this->database()->execute('ALTER TABLE presets DROP COLUMN IF EXISTS stock_price_cents');
    }
}