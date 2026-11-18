<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddOrderTree extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'admin'");

        $this->database()->execute('DROP TABLE IF EXISTS lead_placements');
        $this->database()->execute('DROP TABLE IF EXISTS acceptances');
        $this->database()->execute('DROP TABLE IF EXISTS demands');
        $this->database()->execute('DROP TABLE IF EXISTS slots');
        $this->database()->execute('DROP TYPE IF EXISTS listing_status');
        $this->database()->execute('DROP TYPE IF EXISTS pricing_model');

        $this->database()->execute("CREATE TYPE order_status AS ENUM ('in_progress', 'paused', 'finished')");
        $this->database()->execute("CREATE TYPE offer_status AS ENUM ('pending', 'approved', 'rejected')");

        $this->database()->execute("
            CREATE TABLE presets (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                admin_id UUID NOT NULL REFERENCES users (id),
                traffic_source VARCHAR(64) NOT NULL,
                age VARCHAR(64) NOT NULL,
                gender VARCHAR(64) NOT NULL,
                cr INTEGER NOT NULL CHECK (cr >= 0 AND cr <= 100),
                base_price_cents INTEGER NOT NULL CHECK (base_price_cents > 0),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                UNIQUE (organization_id, traffic_source, age, gender)
            )
        ");

        $this->database()->execute("
            CREATE TABLE orders (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                partner_id UUID NOT NULL REFERENCES users (id),
                preset_id UUID NOT NULL REFERENCES presets (id),
                geo VARCHAR(16) NOT NULL,
                volume INTEGER NOT NULL CHECK (volume > 0),
                price_cents INTEGER NOT NULL CHECK (price_cents > 0),
                stock BOOLEAN NOT NULL,
                service_on DATE,
                opens_at TIME NOT NULL,
                closes_at TIME NOT NULL,
                supplied_count INTEGER NOT NULL DEFAULT 0 CHECK (supplied_count >= 0 AND supplied_count <= volume),
                status order_status NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CHECK ((stock AND service_on IS NULL) OR (NOT stock AND service_on IS NOT NULL)),
                CHECK (opens_at < closes_at)
            )
        ");
        $this->database()->execute('CREATE INDEX orders_organization_id_status_idx ON orders (organization_id, status)');

        $this->database()->execute("
            CREATE TABLE offers (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                buyer_id UUID NOT NULL REFERENCES users (id),
                preset_id UUID NOT NULL REFERENCES presets (id),
                geo VARCHAR(16) NOT NULL,
                volume INTEGER NOT NULL CHECK (volume > 0),
                stock BOOLEAN NOT NULL,
                service_on DATE,
                supplied_count INTEGER NOT NULL DEFAULT 0 CHECK (supplied_count >= 0 AND supplied_count <= volume),
                status offer_status NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CHECK ((stock AND service_on IS NULL) OR (NOT stock AND service_on IS NOT NULL))
            )
        ");
        $this->database()->execute('CREATE INDEX offers_organization_id_status_idx ON offers (organization_id, status)');

        $this->database()->execute('
            CREATE TABLE lead_placements (
                lead_uuid UUID PRIMARY KEY,
                order_id UUID NULL REFERENCES orders (id),
                seller_id UUID NULL REFERENCES users (id),
                price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
                sold_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL,
                CHECK (
                    (
                        order_id IS NULL
                        AND seller_id IS NULL
                        AND sold_at IS NULL
                        AND price_cents = 0
                    )
                    OR (
                        order_id IS NOT NULL
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
        $this->database()->execute('DROP TABLE IF EXISTS offers');
        $this->database()->execute('DROP TABLE IF EXISTS orders');
        $this->database()->execute('DROP TABLE IF EXISTS presets');
        $this->database()->execute('DROP TYPE IF EXISTS offer_status');
        $this->database()->execute('DROP TYPE IF EXISTS order_status');
    }
}
