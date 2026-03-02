<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('spend_request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->foreignId('department_budget_id')->nullable()->constrained('department_budgets')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('po_number', 80);
            $table->string('po_status', 30)->default('draft'); // draft|issued|part_received|received|invoiced|closed|canceled
            $table->string('currency_code', 3)->default('NGN');
            $table->unsignedBigInteger('subtotal_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->dateTime('issued_at')->nullable();
            $table->date('expected_delivery_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->dateTime('canceled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'po_number'], 'po_company_number_unique');
            $table->index(['company_id', 'po_status'], 'po_company_status_idx');
            $table->index(['company_id', 'vendor_id', 'po_status'], 'po_company_vendor_status_idx');
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_number')->default(1);
            $table->string('item_description', 255);
            $table->decimal('quantity', 14, 2)->default(1);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('line_total');
            $table->string('currency_code', 3)->default('NGN');
            $table->decimal('received_quantity', 14, 2)->default(0);
            $table->unsignedBigInteger('received_total')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_number'], 'poi_order_line_unique');
            $table->index(['company_id', 'purchase_order_id'], 'poi_company_order_idx');
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('receipt_number', 80);
            $table->dateTime('received_at');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_status', 30)->default('confirmed'); // draft|confirmed|void
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'receipt_number'], 'gr_company_number_unique');
            $table->index(['company_id', 'purchase_order_id', 'received_at'], 'gr_company_po_received_idx');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->decimal('received_quantity', 14, 2);
            $table->unsignedBigInteger('received_unit_cost')->nullable();
            $table->unsignedBigInteger('received_total')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'purchase_order_item_id'], 'gri_receipt_poi_unique');
            $table->index(['company_id', 'goods_receipt_id'], 'gri_company_receipt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
