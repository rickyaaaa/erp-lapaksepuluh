<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbounds', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('partner_id');
            $table->date('business_date');
            $table->string('photo_path', 255)->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampsTz();

            $table->foreign('branch_id')->references('id')->on('branches')->restrictOnDelete();
            $table->foreign('partner_id')->references('id')->on('partners')->restrictOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['branch_id', 'partner_id', 'business_date'], 'inbounds_branch_partner_date_unique');
            $table->index(['branch_id', 'business_date'], 'inbounds_branch_date_index');
        });

        DB::statement("ALTER TABLE inbounds ADD CONSTRAINT inbounds_status_check CHECK (status IN ('DRAFT', 'LOCKED', 'CORRECTED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inbounds');
    }
};
