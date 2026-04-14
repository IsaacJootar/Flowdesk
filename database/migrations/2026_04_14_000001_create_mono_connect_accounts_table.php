<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the mono_connect_accounts table.
 *
 * A MonoConnectAccount links a Flowdesk BankAccount record to a real bank
 * account via Mono Connect Open Banking.  Once linked, Flowdesk can:
 *
 *   - Pull live transactions (replacing CSV import for treasury reconciliation)
 *   - Read the real-time account balance (for Treasury Cash Position dashboard)
 *   - Verify account metadata (institution, account name)
 *
 * One MonoConnectAccount per BankAccount.  A tenant may have multiple
 * BankAccounts and multiple MonoConnectAccounts.
 *
 * Tenant links their bank account by completing the Mono Connect widget
 * (https://mono.co/connect), which returns an auth code.  Flowdesk exchanges
 * the code for a `mono_account_id` via MonoConnectService::exchangeAuthCode().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mono_connect_accounts', function (Blueprint $table): void {
            $table->id();

            // Tenant scoping — mandatory for all company-owned tables
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->restrictOnDelete();

            // The BankAccount this Mono connection is linked to
            $table->unsignedBigInteger('bank_account_id');
            $table->foreign('bank_account_id')
                ->references('id')->on('bank_accounts')
                ->restrictOnDelete();

            // Mono's unique identifier for the linked bank account
            // This is what all Mono Connect API calls use
            $table->string('mono_account_id', 100)->unique();

            // Bank / account metadata fetched from Mono at link time
            $table->string('institution_name', 200)->nullable(); // e.g. "Access Bank"
            $table->string('account_name', 200)->nullable();     // e.g. "Acme Corp Ltd"
            $table->string('account_number_last4', 4)->nullable();
            $table->string('currency_code', 10)->default('NGN');

            // Live balance — refreshed each time a statement sync runs
            // Stored in KOBO (minor units), consistent with bank_statement_lines.amount
            $table->unsignedBigInteger('balance_amount')->nullable()->comment('Balance in kobo');
            $table->timestamp('balance_synced_at')->nullable();

            $table->boolean('is_active')->default(true)->index();

            // Sync tracking
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable(); // last error message, cleared on success

            // Flexible metadata: raw Mono account response, widget session info, etc.
            $table->json('metadata')->nullable();

            // Audit trail
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Query indexes
            $table->index('company_id');
            $table->index('bank_account_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mono_connect_accounts');
    }
};
