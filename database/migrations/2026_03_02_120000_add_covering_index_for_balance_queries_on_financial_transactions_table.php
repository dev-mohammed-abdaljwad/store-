<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->index(
                ['store_id', 'party_type', 'party_id', 'type', 'amount'],
                'ft_balance_cover_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex('ft_balance_cover_idx');
        });
    }
};
