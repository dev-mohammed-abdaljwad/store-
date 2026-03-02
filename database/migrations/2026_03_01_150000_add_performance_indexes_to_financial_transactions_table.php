<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->index(
                ['store_id', 'party_type', 'party_id', 'type'],
                'ft_store_party_type_idx'
            );

            $table->index(
                ['store_id', 'party_type', 'party_id', 'created_at'],
                'ft_store_party_created_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex('ft_store_party_type_idx');
            $table->dropIndex('ft_store_party_created_at_idx');
        });
    }
};
