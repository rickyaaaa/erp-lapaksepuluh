<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_cycles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('cycle_code', 20);
            $table->string('status', 20)->default('DRAFT');
            $table->unsignedBigInteger('created_by');
            $table->timestampTz('locked_at')->nullable();
            $table->timestampsTz();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['period_start', 'period_end'], 'payout_cycles_period_unique');
        });
        Schema::create('payout_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payout_cycle_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('qty_sold');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->bigInteger('payout_amount');
            $table->string('status', 20)->default('READY');
            $table->timestampsTz();
            $table->foreign('payout_cycle_id')->references('id')->on('payout_cycles')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->unique(['payout_cycle_id', 'branch_id', 'partner_id', 'product_id'], 'payout_items_unique');
        });
        Schema::create('payout_payments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('payout_cycle_id');
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('amount');
            $table->string('bank_reference', 120)->unique();
            $table->string('proof_path', 255)->nullable();
            $table->unsignedBigInteger('paid_by');
            $table->timestampTz('paid_at');
            $table->timestampsTz();
            $table->foreign('payout_cycle_id')->references('id')->on('payout_cycles')->restrictOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->restrictOnDelete();
            $table->foreign('paid_by')->references('id')->on('users')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE payout_cycles ADD CONSTRAINT payout_cycles_status_check CHECK (status IN ('DRAFT', 'READY', 'LOCKED', 'PAID'))");
        DB::statement("ALTER TABLE payout_items ADD CONSTRAINT payout_items_status_check CHECK (status IN ('READY', 'EXCLUDED', 'PAID'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_payments');
        Schema::dropIfExists('payout_items');
        Schema::dropIfExists('payout_cycles');
    }
};
