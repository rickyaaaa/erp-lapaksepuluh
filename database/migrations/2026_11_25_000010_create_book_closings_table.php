<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_closings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('OPEN');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['branch_id', 'period_start', 'period_end'], 'book_closings_period_unique');
        });
        if (DB::getDriverName() === 'pgsql') { DB::statement("ALTER TABLE book_closings ADD CONSTRAINT book_closings_status_check CHECK (status IN ('OPEN', 'CLOSED', 'REOPENED'))"); }
    }

    public function down(): void
    {
        Schema::dropIfExists('book_closings');
    }
};
