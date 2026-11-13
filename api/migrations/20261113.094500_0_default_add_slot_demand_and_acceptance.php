<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddSlotDemandAndAcceptance extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("CREATE TYPE listing_status AS ENUM ('open', 'taken', 'closed')");
        $this->database()->execute("CREATE TYPE pricing_model AS ENUM ('cpl', 'cpa')");

        $this->database()->execute("
            CREATE TABLE slots (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                buyer_id UUID NOT NULL REFERENCES users (id),
                geo VARCHAR(16) NOT NULL,
                volume INTEGER NOT NULL CHECK (volume > 0),
                price_cents INTEGER NOT NULL CHECK (price_cents > 0),
                pricing_model pricing_model NOT NULL,
                deadline TIMESTAMPTZ NOT NULL,
                status listing_status NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX slots_organization_id_status_idx ON slots (organization_id, status)');

        $this->database()->execute("
            CREATE TABLE demands (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                partner_id UUID NOT NULL REFERENCES users (id),
                geo VARCHAR(16) NOT NULL,
                volume INTEGER NOT NULL CHECK (volume > 0),
                price_cents INTEGER NOT NULL CHECK (price_cents > 0),
                pricing_model pricing_model NOT NULL,
                status listing_status NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX demands_organization_id_status_idx ON demands (organization_id, status)');

        $this->database()->execute("
            CREATE TABLE acceptances (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                slot_id UUID REFERENCES slots (id),
                demand_id UUID REFERENCES demands (id),
                partner_id UUID NOT NULL REFERENCES users (id),
                buyer_id UUID NOT NULL REFERENCES users (id),
                volume INTEGER NOT NULL CHECK (volume > 0),
                buyer_price_cents INTEGER NOT NULL CHECK (buyer_price_cents > 0),
                partner_price_cents INTEGER NOT NULL CHECK (partner_price_cents >= buyer_price_cents),
                pricing_model pricing_model NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CHECK ((slot_id IS NOT NULL) <> (demand_id IS NOT NULL)),
                CHECK (partner_id <> buyer_id)
            )
        ");
        $this->database()->execute('
            CREATE UNIQUE INDEX acceptances_slot_id_uidx
            ON acceptances (slot_id)
            WHERE slot_id IS NOT NULL
        ');
        $this->database()->execute('
            CREATE UNIQUE INDEX acceptances_demand_id_uidx
            ON acceptances (demand_id)
            WHERE demand_id IS NOT NULL
        ');
        $this->database()->execute('CREATE INDEX acceptances_organization_id_idx ON acceptances (organization_id)');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS acceptances');
        $this->database()->execute('DROP TABLE IF EXISTS demands');
        $this->database()->execute('DROP TABLE IF EXISTS slots');
        $this->database()->execute('DROP TYPE IF EXISTS pricing_model');
        $this->database()->execute('DROP TYPE IF EXISTS listing_status');
    }
}
