<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('promotions')->orderBy('id')->get()->each(function (object $promotion): void {
            DB::table('products')
                ->where('id', $promotion->product_id)
                ->whereNull('promotion_id')
                ->update(['promotion_id' => $promotion->id]);
        });

        Schema::table('promotions', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('products')
            ->whereNotNull('promotion_id')
            ->orderBy('id')
            ->get(['id', 'promotion_id'])
            ->each(function (object $product): void {
                DB::table('promotions')
                    ->where('id', $product->promotion_id)
                    ->whereNull('product_id')
                    ->update(['product_id' => $product->id]);
            });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn('promotion_id');
        });
    }
};
