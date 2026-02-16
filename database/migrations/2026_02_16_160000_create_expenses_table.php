<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('expense_code');
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount');
            $table->date('expense_date');
            $table->string('payment_method')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('posted');
            $table->boolean('is_direct')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'expense_code']);
            $table->index('company_id');
            $table->index('department_id');
            $table->index('vendor_id');
            $table->index('payment_method');
            $table->index('status');
            $table->index('expense_date');
            $table->index('created_by');
            $table->index('paid_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
