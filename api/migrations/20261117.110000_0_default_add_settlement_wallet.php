<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddSettlementWallet extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute('
            ALTER TABLE organizations
            ADD COLUMN balance INTEGER NOT NULL DEFAULT 0 CHECK (balance >= 0)
        ');

        $this->database()->execute("
            CREATE TYPE wallet_transaction_kind AS ENUM (
                'adjustment',
                'partner_lead_charge',
                'buyer_lead_credit',
                'org_platform_fee'
            )
        ");

        $this->database()->execute('ALTER TABLE wallet_transactions ADD COLUMN kind wallet_transaction_kind NOT NULL DEFAULT \'adjustment\'');
        $this->database()->execute('ALTER TABLE wallet_transactions ADD COLUMN lead_uuid UUID NULL');
        $this->database()->execute('ALTER TABLE wallet_transactions ADD COLUMN order_id UUID NULL');
        $this->database()->execute("ALTER TABLE wallet_transactions ADD COLUMN metadata JSONB NOT NULL DEFAULT '{}'");
        $this->database()->execute('ALTER TABLE wallet_transactions ALTER COLUMN user_id DROP NOT NULL');
        $this->database()->execute('CREATE INDEX wallet_transactions_lead_uuid_idx ON wallet_transactions (lead_uuid)');
        $this->database()->execute('CREATE INDEX wallet_transactions_order_id_idx ON wallet_transactions (order_id)');

        $this->database()->execute('
            CREATE TABLE wallet_settlement_claims (
                lead_uuid UUID NOT NULL,
                kind VARCHAR(64) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                PRIMARY KEY (lead_uuid, kind)
            )
        ');

        $this->database()->execute('
            CREATE TABLE lead_conversion_notes (
                lead_uuid UUID PRIMARY KEY,
                outcome VARCHAR(32) NOT NULL,
                reason TEXT NOT NULL DEFAULT \'\',
                actor_id UUID NOT NULL REFERENCES users (id),
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS lead_conversion_notes');
        $this->database()->execute('DROP TABLE IF EXISTS wallet_settlement_claims');
        $this->database()->execute('ALTER TABLE wallet_transactions DROP COLUMN IF EXISTS metadata');
        $this->database()->execute('ALTER TABLE wallet_transactions DROP COLUMN IF EXISTS order_id');
        $this->database()->execute('ALTER TABLE wallet_transactions DROP COLUMN IF EXISTS lead_uuid');
        $this->database()->execute('ALTER TABLE wallet_transactions DROP COLUMN IF EXISTS kind');
        $this->database()->execute('ALTER TABLE wallet_transactions ALTER COLUMN user_id SET NOT NULL');
        $this->database()->execute('DROP TYPE IF EXISTS wallet_transaction_kind');
        $this->database()->execute('ALTER TABLE organizations DROP COLUMN IF EXISTS balance');
    }
}