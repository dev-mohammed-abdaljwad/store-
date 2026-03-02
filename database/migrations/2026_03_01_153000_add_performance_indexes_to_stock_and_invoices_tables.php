<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['store_id', 'product_id', 'type'], 'sm_store_product_type_idx');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->index(['store_id', 'created_at'], 'si_store_created_at_idx');
            $table->index(['store_id', 'status', 'created_at'], 'si_store_status_created_at_idx');
            $table->index(['store_id', 'customer_id', 'created_at'], 'si_store_customer_created_at_idx');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->index(['store_id', 'created_at'], 'pi_store_created_at_idx');
            $table->index(['store_id', 'status', 'created_at'], 'pi_store_status_created_at_idx');
            $table->index(['store_id', 'supplier_id', 'created_at'], 'pi_store_supplier_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('sm_store_product_type_idx');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('si_store_created_at_idx');
            $table->dropIndex('si_store_status_created_at_idx');
            $table->dropIndex('si_store_customer_created_at_idx');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('pi_store_created_at_idx');
            $table->dropIndex('pi_store_status_created_at_idx');
            $table->dropIndex('pi_store_supplier_created_at_idx');
        });
    }
};
