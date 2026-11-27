<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_expenses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('daily_closing_id');
            $table->string('category', 40);
            $table->unsignedBigInteger('amount');
            $table->string('description', 200);
            $table->string('proof_path', 255)->nullable();
            $table->boolean('is_valid')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestampsTz();

            $table->foreign('daily_closing_id')->references('id')->on('daily_closings')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['daily_closing_id', 'is_valid'], 'closing_expenses_closing_valid_index');
        });

        if (DB::getDriverName() === 'pgsql') { DB::statement('ALTER TABLE closing_expenses ADD CONSTRAINT closing_expenses_amount_check CHECK (amount > 0)'); }
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_expenses');
    }
};
