<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddUserMetaBalanceAndWallet extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("ALTER TABLE users ADD COLUMN meta JSONB NOT NULL DEFAULT '{}'");
        $this->database()->execute('ALTER TABLE users ADD COLUMN balance INTEGER NOT NULL DEFAULT 0 CHECK (balance >= 0)');

        $this->database()->execute("CREATE TYPE wallet_direction AS ENUM ('credit', 'debit')");
        $this->database()->execute("
            CREATE TABLE wallet_transactions (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                user_id UUID NOT NULL REFERENCES users (id),
                direction wallet_direction NOT NULL,
                amount_cents INTEGER NOT NULL CHECK (amount_cents > 0),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE INDEX wallet_transactions_user_id_idx ON wallet_transactions (user_id)');
        $this->database()->execute('CREATE INDEX wallet_transactions_organization_id_idx ON wallet_transactions (organization_id)');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS wallet_transactions');
        $this->database()->execute('DROP TYPE IF EXISTS wallet_direction');
        $this->database()->execute('ALTER TABLE users DROP COLUMN IF EXISTS balance');
        $this->database()->execute('ALTER TABLE users DROP COLUMN IF EXISTS meta');
    }
}
