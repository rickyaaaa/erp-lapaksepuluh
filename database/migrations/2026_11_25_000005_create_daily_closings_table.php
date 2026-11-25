<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->date('business_date');
            $table->unsignedBigInteger('cashier_id');
            $table->unsignedBigInteger('system_gross')->default(0);
            $table->unsignedBigInteger('counted_cash')->default(0);
            $table->unsignedBigInteger('qris_settlement')->default(0);
            $table->unsignedBigInteger('total_valid_expenses')->default(0);
            $table->unsignedBigInteger('total_accountability')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->string('difference_status', 10)->default('KLOP');
            $table->string('status', 20)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('cashier_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['branch_id', 'business_date'], 'daily_closings_branch_date_unique');
        });

        DB::statement("ALTER TABLE daily_closings ADD CONSTRAINT daily_closings_difference_status_check CHECK (difference_status IN ('KLOP', 'MINUS', 'PLUS'))");
        DB::statement("ALTER TABLE daily_closings ADD CONSTRAINT daily_closings_status_check CHECK (status IN ('DRAFT', 'SUBMITTED', 'VERIFIED', 'LOCKED_SETTLED', 'REJECTED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
