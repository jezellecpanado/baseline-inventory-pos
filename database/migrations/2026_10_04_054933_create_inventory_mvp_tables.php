<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('design_name');
            $table->string('category');
            $table->string('variant');
            $table->string('size');
            $table->char('barcode', 8)->unique();
            $table->decimal('standard_price', 10, 2);
            $table->string('photo_path')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->unique(['design_name', 'variant', 'size']);
            $table->index(['category', 'variant', 'size']);
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('warehouse_quantity')->default(0);
            $table->unsignedInteger('pos_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('required_quantity');
            $table->decimal('bundle_price', 10, 2);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('submission_key', 64)->nullable()->unique();
            $table->string('type')->index();
            $table->string('status')->default('completed')->index();
            $table->string('location')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name');
            $table->timestamp('completed_at')->index();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voided_by_name')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['type', 'completed_at']);
        });

        Schema::create('transaction_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('category');
            $table->string('variant');
            $table->string('size');
            $table->char('barcode', 8);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->string('condition')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'created_at']);
        });

        Schema::create('inventory_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->index();
            $table->string('movement_type')->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('category');
            $table->string('variant');
            $table->string('size');
            $table->char('barcode', 8);
            $table->string('location')->index();
            $table->integer('quantity_change');
            $table->foreignId('inventory_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['location', 'created_at']);
        });

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('updated_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('inventory_histories');
        Schema::dropIfExists('transaction_lines');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('products');
    }
};
