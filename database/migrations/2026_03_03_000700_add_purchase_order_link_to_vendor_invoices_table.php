<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('purchase_orders')
                ->nullOnDelete();

            $table->index(['company_id', 'purchase_order_id'], 'vendor_invoice_company_po_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table): void {
            $table->dropIndex('vendor_invoice_company_po_idx');
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};