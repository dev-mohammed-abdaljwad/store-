<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropIndex('products_store_deleted_sku_idx');
            } catch (Throwable $e) {
            }

            try {
                $table->dropUnique('products_store_id_sku_deleted_at_unique');
            } catch (Throwable $e) {
            }

            $table->dropColumn([
                'sku',
                'unit',
                'purchase_price',
                'sale_price',
                'low_stock_threshold',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('name');
            $table->string('unit')->default('قطعة')->after('sku');
            $table->decimal('purchase_price', 12, 2)->default(0)->after('unit');
            $table->decimal('sale_price', 12, 2)->default(0)->after('purchase_price');
            $table->integer('low_stock_threshold')->default(0)->after('sale_price');

            $table->unique(['store_id', 'sku', 'deleted_at']);
            $table->index(['store_id', 'deleted_at', 'sku'], 'products_store_deleted_sku_idx');
        });
    }
};
