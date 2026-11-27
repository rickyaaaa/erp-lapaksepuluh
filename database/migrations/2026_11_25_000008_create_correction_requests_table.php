<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('field_name', 80);
            $table->text('original_value')->nullable();
            $table->text('proposed_value');
            $table->text('reason');
            $table->string('status', 20)->default('PENDING');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampsTz();
            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['branch_id', 'status'], 'corrections_branch_status_index');
            $table->index(['entity_type', 'entity_id'], 'corrections_entity_index');
        });

        if (DB::getDriverName() === 'pgsql') { DB::statement("ALTER TABLE correction_requests ADD CONSTRAINT correction_requests_status_check CHECK (status IN ('PENDING', 'APPROVED', 'REJECTED'))"); }
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
