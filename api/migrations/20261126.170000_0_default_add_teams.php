<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddTeams extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            CREATE TABLE team_units (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                account_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                parent_id UUID REFERENCES team_units (id),
                name VARCHAR(80) NOT NULL,
                budget_cents INTEGER,
                budget_period VARCHAR(8) NOT NULL DEFAULT 'total',
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX team_units_account_id_idx ON team_units (account_id)');

        $this->database()->execute("
            CREATE TABLE team_members (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                account_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                unit_id UUID REFERENCES team_units (id),
                email VARCHAR(255) NOT NULL,
                identity_id UUID,
                permissions JSONB NOT NULL DEFAULT '[]'::jsonb,
                expires_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE UNIQUE INDEX team_members_email_uidx ON team_members (email)');
        $this->database()->execute('CREATE UNIQUE INDEX team_members_identity_id_uidx ON team_members (identity_id)');
        $this->database()->execute('CREATE INDEX team_members_account_id_idx ON team_members (account_id)');

        $this->database()->execute("
            CREATE TABLE team_orders (
                order_id UUID PRIMARY KEY REFERENCES orders (id) ON DELETE CASCADE,
                account_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                unit_id UUID REFERENCES team_units (id),
                opened_by VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX team_orders_account_id_idx ON team_orders (account_id)');
        $this->database()->execute('CREATE INDEX team_orders_unit_id_idx ON team_orders (unit_id)');
        $this->database()->execute('CREATE INDEX lead_placements_order_id_idx ON lead_placements (order_id)');

        $this->database()->execute("
            CREATE TABLE team_budget_changes (
                id UUID PRIMARY KEY,
                unit_id UUID NOT NULL REFERENCES team_units (id) ON DELETE CASCADE,
                budget_cents INTEGER,
                budget_period VARCHAR(8) NOT NULL DEFAULT 'total',
                changed_by VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX team_budget_changes_unit_id_idx ON team_budget_changes (unit_id)');

        $this->database()->execute("
            CREATE TABLE team_budget_alerts (
                id UUID PRIMARY KEY,
                unit_id UUID NOT NULL REFERENCES team_units (id) ON DELETE CASCADE,
                period_start TIMESTAMPTZ NOT NULL,
                threshold SMALLINT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE UNIQUE INDEX team_budget_alerts_uidx ON team_budget_alerts (unit_id, period_start, threshold)');

        $this->database()->execute("
            CREATE TABLE team_events (
                id UUID PRIMARY KEY,
                account_id UUID NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                unit_id UUID,
                actor VARCHAR(255) NOT NULL,
                action VARCHAR(40) NOT NULL,
                details JSONB NOT NULL DEFAULT '{}'::jsonb,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX team_events_account_id_created_at_idx ON team_events (account_id, created_at DESC)');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE team_events');
        $this->database()->execute('DROP TABLE team_budget_alerts');
        $this->database()->execute('DROP TABLE team_budget_changes');
        $this->database()->execute('DROP INDEX lead_placements_order_id_idx');
        $this->database()->execute('DROP TABLE team_orders');
        $this->database()->execute('DROP TABLE team_members');
        $this->database()->execute('DROP TABLE team_units');
    }
}
