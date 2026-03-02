<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit'); // كيلو / لتر / كرتونة
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->integer('low_stock_threshold')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->unique(['store_id', 'sku', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
