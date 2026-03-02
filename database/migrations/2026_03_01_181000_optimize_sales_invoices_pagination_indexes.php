<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('si_store_created_at_idx');
            $table->dropIndex('si_store_status_created_at_idx');
            $table->dropIndex('si_store_customer_created_at_idx');

            $table->index(['store_id', 'deleted_at', 'created_at'], 'si_store_deleted_created_at_idx');
            $table->index(['store_id', 'deleted_at', 'status', 'created_at'], 'si_store_deleted_status_created_at_idx');
            $table->index(['store_id', 'deleted_at', 'customer_id', 'created_at'], 'si_store_deleted_customer_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('si_store_deleted_created_at_idx');
            $table->dropIndex('si_store_deleted_status_created_at_idx');
            $table->dropIndex('si_store_deleted_customer_created_at_idx');

            $table->index(['store_id', 'created_at'], 'si_store_created_at_idx');
            $table->index(['store_id', 'status', 'created_at'], 'si_store_status_created_at_idx');
            $table->index(['store_id', 'customer_id', 'created_at'], 'si_store_customer_created_at_idx');
        });
    }
};
