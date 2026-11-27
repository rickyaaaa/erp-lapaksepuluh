<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->date('business_date');
            $table->string('status', 20)->default('DRAFT');
            $table->unsignedBigInteger('total_gross')->default(0);
            $table->unsignedBigInteger('total_commission')->default(0);
            $table->bigInteger('total_net')->default(0);
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['branch_id', 'business_date'], 'stock_opnames_branch_date_unique');
        });

        if (DB::getDriverName() === 'pgsql') { DB::statement("ALTER TABLE stock_opnames ADD CONSTRAINT stock_opnames_status_check CHECK (status IN ('DRAFT', 'SUBMITTED', 'VERIFIED', 'REJECTED'))"); }
        if (DB::getDriverName() === 'pgsql') { DB::statement('ALTER TABLE stock_opnames ADD CONSTRAINT stock_opnames_totals_check CHECK (total_gross >= 0 AND total_commission >= 0 AND total_net >= 0)'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
