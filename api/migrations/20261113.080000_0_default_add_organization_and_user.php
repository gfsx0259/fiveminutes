<?php

declare(strict_types=1);

namespace App\Migration;

use Cycle\Migrations\Migration;

class AddOrganizationAndUser extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        $this->database()->execute("
            CREATE TABLE organizations (
                id UUID PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                apex VARCHAR(255) NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE UNIQUE INDEX organizations_apex_uidx ON organizations (apex)');

        $this->database()->execute("CREATE TYPE user_role AS ENUM ('buyer', 'partner')");
        $this->database()->execute("
            CREATE TABLE users (
                id UUID PRIMARY KEY,
                organization_id UUID NOT NULL REFERENCES organizations (id),
                identity_id UUID,
                email VARCHAR(255) NOT NULL,
                role user_role NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");
        $this->database()->execute('CREATE UNIQUE INDEX users_email_uidx ON users (email)');
        $this->database()->execute('
            CREATE UNIQUE INDEX users_identity_id_uidx
            ON users (identity_id)
            WHERE identity_id IS NOT NULL
        ');
        $this->database()->execute('CREATE INDEX users_organization_id_idx ON users (organization_id)');
    }

    public function down(): void
    {
        $this->database()->execute('DROP TABLE IF EXISTS users');
        $this->database()->execute('DROP TYPE IF EXISTS user_role');
        $this->database()->execute('DROP TABLE IF EXISTS organizations');
    }
}
