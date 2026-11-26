<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_trails', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event', 80);
            $table->string('auditable_type', 80);
            $table->unsignedBigInteger('auditable_id');
            $table->jsonb('before_data')->nullable();
            $table->jsonb('after_data')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_index');
            $table->index(['branch_id', 'created_at'], 'audit_branch_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
