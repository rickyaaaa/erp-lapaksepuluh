<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_audit_trail_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_trails bersifat immutable';
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_trails_immutable_trigger
            BEFORE UPDATE OR DELETE ON audit_trails
            FOR EACH ROW EXECUTE FUNCTION reject_audit_trail_mutation()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_trails_immutable_trigger ON audit_trails');
        DB::statement('DROP FUNCTION IF EXISTS reject_audit_trail_mutation()');
    }
};
