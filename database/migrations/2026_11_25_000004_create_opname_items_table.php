<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('opname_id');
            $table->unsignedBigInteger('inbound_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('partner_id');
            $table->unsignedInteger('qty_in_final');
            $table->unsignedInteger('qty_remaining_final');
            $table->unsignedInteger('qty_damaged')->default(0);
            $table->unsignedInteger('qty_sold');
            $table->unsignedBigInteger('price_snapshot');
            $table->unsignedBigInteger('commission_snapshot');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_amount');
            $table->timestampsTz();

            $table->foreign('opname_id')->references('id')->on('stock_opnames')->cascadeOnDelete();
            $table->foreign('inbound_item_id')->references('id')->on('inbound_items')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->restrictOnDelete();
            $table->unique(['opname_id', 'inbound_item_id'], 'opname_items_opname_inbound_unique');
            $table->index(['product_id', 'partner_id'], 'opname_items_product_partner_index');
        });

        if (DB::getDriverName() === 'pgsql') { DB::statement('ALTER TABLE opname_items ADD CONSTRAINT opname_items_remaining_check CHECK (qty_remaining_final <= qty_in_final)'); }
        if (DB::getDriverName() === 'pgsql') { DB::statement('ALTER TABLE opname_items ADD CONSTRAINT opname_items_damaged_check CHECK (qty_damaged <= qty_remaining_final)'); }
        if (DB::getDriverName() === 'pgsql') { DB::statement('ALTER TABLE opname_items ADD CONSTRAINT opname_items_sold_check CHECK (qty_sold = qty_in_final - qty_remaining_final)'); }
        if (DB::getDriverName() === 'pgsql') { DB::statement('ALTER TABLE opname_items ADD CONSTRAINT opname_items_amounts_check CHECK (gross_amount >= 0 AND commission_amount >= 0 AND net_amount >= 0)'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_items');
    }
};
