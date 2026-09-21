<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title'); $table->text('notes')->nullable(); $table->string('currency', 8)->default('KES');
            $table->decimal('subtotal', 12, 2)->default(0); $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0); $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status')->default('draft'); $table->text('rejection_reason')->nullable(); $table->date('due_date')->nullable();
            $table->timestamp('sent_at')->nullable(); $table->timestamp('responded_at')->nullable(); $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->index(['school_id', 'status']);
        });
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description'); $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2); $table->decimal('amount', 12, 2); $table->unsignedInteger('position')->default(0); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('invoice_items'); Schema::dropIfExists('invoices'); }
};
