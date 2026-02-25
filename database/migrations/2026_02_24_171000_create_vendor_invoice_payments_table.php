<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->cascadeOnDelete();
            $table->string('payment_reference', 80)->nullable();
            $table->unsignedBigInteger('amount');
            $table->date('payment_date');
            $table->string('payment_method', 40)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'payment_reference'], 'vendor_payment_company_reference_unique');
            $table->index(['company_id', 'vendor_id', 'payment_date'], 'vendor_payment_company_vendor_date_idx');
            $table->index(['company_id', 'vendor_invoice_id'], 'vendor_payment_company_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_payments');
    }
};

