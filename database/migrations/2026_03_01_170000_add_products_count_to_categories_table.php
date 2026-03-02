<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('products_count')->default(0)->after('name');
        });

        DB::table('categories')->update(['products_count' => 0]);

        $counts = DB::table('products')
            ->selectRaw('store_id, category_id, COUNT(*) as total')
            ->whereNull('deleted_at')
            ->groupBy('store_id', 'category_id')
            ->get();

        foreach ($counts as $count) {
            DB::table('categories')
                ->where('id', $count->category_id)
                ->where('store_id', $count->store_id)
                ->update(['products_count' => (int) $count->total]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('products_count');
        });
    }
};
