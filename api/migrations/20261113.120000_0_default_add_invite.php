<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddInvite extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            CREATE TABLE invites (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                email VARCHAR(255) NOT NULL,
                role user_role NOT NULL,
                active BOOLEAN NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE UNIQUE INDEX invites_email_uidx ON invites (email)');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE invites');
    }
}
