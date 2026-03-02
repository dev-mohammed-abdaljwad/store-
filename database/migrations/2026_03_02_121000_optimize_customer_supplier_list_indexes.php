<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_store_deleted_name_idx');
            $table->index(
                ['store_id', 'deleted_at', 'name', 'id', 'phone'],
                'customers_store_deleted_name_cover_idx'
            );
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_store_deleted_name_idx');
            $table->index(
                ['store_id', 'deleted_at', 'name', 'id', 'phone'],
                'suppliers_store_deleted_name_cover_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_store_deleted_name_cover_idx');
            $table->index(
                ['store_id', 'deleted_at', 'name'],
                'customers_store_deleted_name_idx'
            );
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('suppliers_store_deleted_name_cover_idx');
            $table->index(
                ['store_id', 'deleted_at', 'name'],
                'suppliers_store_deleted_name_idx'
            );
        });
    }
};
