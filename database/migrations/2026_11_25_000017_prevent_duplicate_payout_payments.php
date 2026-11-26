<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_payments', function (Blueprint $table): void {
            $table->unique(['payout_cycle_id', 'partner_id'], 'payout_payments_cycle_partner_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payout_payments', function (Blueprint $table): void {
            $table->dropUnique('payout_payments_cycle_partner_unique');
        });
    }
};
