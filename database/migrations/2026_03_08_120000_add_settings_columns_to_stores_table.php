<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('address');
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('print_header')->nullable()->after('logo_path');
            $table->string('print_phone')->nullable()->after('print_header');
            $table->string('print_address')->nullable()->after('print_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique('stores_slug_unique');
            $table->dropColumn([
                'slug',
                'logo_path',
                'print_header',
                'print_phone',
                'print_address',
            ]);
        });
    }
};
