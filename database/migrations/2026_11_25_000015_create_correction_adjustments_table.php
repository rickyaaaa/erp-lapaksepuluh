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
            $table->string('payout_status', 20)->default('NOT_PAID')->after('payout_delta');
        });
        Schema::create('correction_adjustments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('correction_request_id');
            $table->bigInteger('payout_delta');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedBigInteger('created_by');
            $table->timestampsTz();
            $table->foreign('correction_request_id')->references('id')->on('correction_requests')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE correction_requests ADD CONSTRAINT correction_requests_payout_status_check CHECK (payout_status IN ('NOT_PAID', 'PAID'))");
        DB::statement("ALTER TABLE correction_adjustments ADD CONSTRAINT correction_adjustments_status_check CHECK (status IN ('PENDING', 'APPLIED', 'SETTLED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_adjustments');
        Schema::table('correction_requests', function (Blueprint $table): void {
            $table->dropColumn('payout_status');
        });
    }
};
