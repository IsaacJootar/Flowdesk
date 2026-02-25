<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_communication_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->restrictOnDelete();
            $table->string('event');
            $table->string('channel');
            $table->string('status')->default('queued');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->date('reminder_date');
            $table->string('dedupe_key', 190)->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('vendor_id');
            $table->index('vendor_invoice_id');
            $table->index('event');
            $table->index('channel');
            $table->index('status');
            $table->index('reminder_date');
            $table->index('dedupe_key');
            $table->unique(['company_id', 'dedupe_key'], 'vendor_comm_logs_unique_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_communication_logs');
    }
};
