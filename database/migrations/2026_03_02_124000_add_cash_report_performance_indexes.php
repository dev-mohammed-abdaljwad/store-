<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->index(
                ['store_id', 'transaction_date', 'type', 'amount'],
                'cash_store_date_type_amount_idx'
            );

            $table->index(
                ['store_id', 'type', 'amount'],
                'cash_store_type_amount_idx'
            );

            $table->index(
                ['store_id', 'transaction_date', 'created_at', 'id'],
                'cash_store_date_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropIndex('cash_store_date_type_amount_idx');
            $table->dropIndex('cash_store_type_amount_idx');
            $table->dropIndex('cash_store_date_created_idx');
        });
    }
};
