<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('correction_requests', function (Blueprint $table): void {
            $table->string('payout_impact', 20)->default('UNKNOWN')->after('review_note');
            $table->bigInteger('payout_delta')->default(0)->after('payout_impact');
        });
        DB::statement("ALTER TABLE correction_requests ADD CONSTRAINT correction_requests_payout_impact_check CHECK (payout_impact IN ('UNKNOWN', 'INCREASE', 'DECREASE', 'UNCHANGED', 'BLOCKED'))");
    }

    public function down(): void
    {
        Schema::table('correction_requests', function (Blueprint $table): void {
            $table->dropColumn(['payout_impact', 'payout_delta']);
        });
    }
};
