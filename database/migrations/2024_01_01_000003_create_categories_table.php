<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index('store_id');
            $table->unique(['store_id', 'name', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
