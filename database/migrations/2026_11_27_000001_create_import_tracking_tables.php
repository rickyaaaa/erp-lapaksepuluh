<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_file', 255);
            $table->string('status', 20)->default('DRY_RUN');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('flagged_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->string('partner_code', 40)->nullable();
            $table->string('sku_code', 60)->nullable();
            $table->string('status', 20);
            $table->text('normalization_notes')->nullable();
            $table->text('error_message')->nullable();
            $table->json('source_values');
            $table->timestampsTz();
            $table->unique(['import_batch_id', 'source_row'], 'import_rows_batch_row_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
