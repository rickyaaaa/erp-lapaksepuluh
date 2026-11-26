<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_mutations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('book_closing_id')->nullable();
            $table->date('mutation_date');
            $table->string('reference', 120)->unique();
            $table->string('direction', 10);
            $table->bigInteger('amount');
            $table->string('description', 255);
            $table->string('status', 20)->default('UNMATCHED');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('book_closing_id')->references('id')->on('book_closings')->nullOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['branch_id', 'mutation_date'], 'bank_mutations_branch_date_index');
        });
        DB::statement("ALTER TABLE bank_mutations ADD CONSTRAINT bank_mutations_direction_check CHECK (direction IN ('IN', 'OUT'))");
        DB::statement("ALTER TABLE bank_mutations ADD CONSTRAINT bank_mutations_status_check CHECK (status IN ('UNMATCHED', 'MATCHED', 'CONFIRMED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_mutations');
    }
};
