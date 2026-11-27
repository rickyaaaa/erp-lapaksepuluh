<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('home_branch_id')->nullable()->after('password')->constrained('branches')->nullOnDelete();
            $table->string('role', 30)->default('KASIR')->after('home_branch_id');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 40)->unique();
            $table->timestampsTz();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestampsTz();
        });
        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('user_branches', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['user_id', 'branch_id']);
        });

        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_name', 160)->nullable();
            $table->string('bank_account_number', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku_code', 60)->unique();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
        Schema::create('branch_products', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->primary(['branch_id', 'product_id']);
        });
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('commission');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestampsTz();
            $table->index(['product_id', 'branch_id', 'effective_from'], 'product_prices_lookup_index');
        });
        Schema::create('price_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('proposed_price');
            $table->unsignedBigInteger('proposed_commission');
            $table->text('reason');
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['product_id', 'status'], 'price_change_product_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_requests');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('branch_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('home_branch_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('branches');
    }
};
