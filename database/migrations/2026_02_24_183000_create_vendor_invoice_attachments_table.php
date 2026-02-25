<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_invoice_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vendor_id'], 'vendor_invoice_attach_company_vendor_idx');
            $table->index(['company_id', 'vendor_invoice_id'], 'vendor_invoice_attach_company_invoice_idx');
            $table->index(['uploaded_by'], 'vendor_invoice_attach_uploaded_by_idx');
            $table->index(['uploaded_at'], 'vendor_invoice_attach_uploaded_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_attachments');
    }
};

