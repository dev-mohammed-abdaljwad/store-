<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('invoice_number');
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed');
            $table->text('notes')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'invoice_number']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
