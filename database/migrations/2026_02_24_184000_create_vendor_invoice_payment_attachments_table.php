<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_invoice_payment_attachments')) {
            return;
        }

        Schema::create('vendor_invoice_payment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id');
            $table->foreignId('vendor_id');
            $table->foreignId('vendor_invoice_id');
            $table->foreignId('vendor_invoice_payment_id');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vendor_id'], 'vendor_payment_attach_company_vendor_idx');
            $table->index(['company_id', 'vendor_invoice_id'], 'vendor_payment_attach_company_invoice_idx');
            $table->index(['company_id', 'vendor_invoice_payment_id'], 'vendor_payment_attach_company_payment_idx');
            $table->index(['uploaded_by'], 'vendor_payment_attach_uploaded_by_idx');
            $table->index(['uploaded_at'], 'vendor_payment_attach_uploaded_at_idx');

            $table->foreign('company_id', 'vip_attach_company_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();
            $table->foreign('vendor_id', 'vip_attach_vendor_fk')
                ->references('id')
                ->on('vendors')
                ->cascadeOnDelete();
            $table->foreign('vendor_invoice_id', 'vip_attach_invoice_fk')
                ->references('id')
                ->on('vendor_invoices')
                ->cascadeOnDelete();
            $table->foreign('vendor_invoice_payment_id', 'vip_attach_payment_fk')
                ->references('id')
                ->on('vendor_invoice_payments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_payment_attachments');
    }
};
