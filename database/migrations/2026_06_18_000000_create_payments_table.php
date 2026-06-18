<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('party_type')->index();
            $table->unsignedBigInteger('party_id')->index();
            $table->decimal('amount', 15, 2);
            $table->string('payment_number')->nullable()->index();
            $table->date('payment_date')->nullable();
            $table->text('description')->nullable();
            $table->string('receipt_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            // Optionally add foreign keys if users/stores tables exist
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
