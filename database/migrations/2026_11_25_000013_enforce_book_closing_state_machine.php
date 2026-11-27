<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_book_closing_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.status = 'CLOSED' AND NEW.status <> 'REOPENED' THEN
                    RAISE EXCEPTION 'Tutup buku CLOSED terkunci dan harus dibuka kembali secara eksplisit';
                END IF;
                IF OLD.status = 'OPEN' AND NEW.status = 'REOPENED' THEN
                    RAISE EXCEPTION 'Tutup buku OPEN tidak dapat langsung menjadi REOPENED';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER book_closings_state_machine_trigger
            BEFORE UPDATE ON book_closings
            FOR EACH ROW EXECUTE FUNCTION enforce_book_closing_state_machine()
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') { DB::statement('DROP TRIGGER IF EXISTS book_closings_state_machine_trigger ON book_closings'); }
        if (DB::getDriverName() === 'pgsql') { DB::statement('DROP FUNCTION IF EXISTS enforce_book_closing_state_machine()'); }
    }
};
