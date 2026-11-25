<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('inbound_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('qty_in');
            $table->unsignedBigInteger('price_snapshot');
            $table->unsignedBigInteger('commission_snapshot');
            $table->timestampsTz();

            $table->foreign('inbound_id')->references('id')->on('inbounds')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->unique(['inbound_id', 'product_id'], 'inbound_items_inbound_product_unique');
            $table->index('product_id', 'inbound_items_product_index');
        });

        DB::statement('ALTER TABLE inbound_items ADD CONSTRAINT inbound_items_qty_in_check CHECK (qty_in > 0)');
        DB::statement('ALTER TABLE inbound_items ADD CONSTRAINT inbound_items_price_snapshot_check CHECK (price_snapshot >= 0)');
        DB::statement('ALTER TABLE inbound_items ADD CONSTRAINT inbound_items_commission_snapshot_check CHECK (commission_snapshot >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_items');
    }
};
