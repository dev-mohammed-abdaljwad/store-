<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->enum('party_type', ['customer', 'supplier']);
            $table->unsignedBigInteger('party_id');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type');             // sales_invoice / purchase_invoice / payment / cancellation
            $table->unsignedBigInteger('reference_id');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'party_type', 'party_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
