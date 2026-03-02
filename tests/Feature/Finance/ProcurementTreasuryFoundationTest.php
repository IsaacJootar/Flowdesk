<?php

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcurementTreasuryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_foundation_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('purchase_orders'));
        $this->assertTrue(Schema::hasTable('purchase_order_items'));
        $this->assertTrue(Schema::hasTable('goods_receipts'));
        $this->assertTrue(Schema::hasTable('goods_receipt_items'));
        $this->assertTrue(Schema::hasTable('procurement_commitments'));
        $this->assertTrue(Schema::hasTable('invoice_match_results'));
        $this->assertTrue(Schema::hasTable('invoice_match_exceptions'));

        $this->assertTrue(Schema::hasColumns('purchase_orders', [
            'company_id',
            'spend_request_id',
            'department_budget_id',
            'vendor_id',
            'po_number',
            'po_status',
            'total_amount',
        ]));

        $this->assertTrue(Schema::hasColumns('invoice_match_results', [
            'purchase_order_id',
            'vendor_invoice_id',
            'match_status',
            'match_score',
        ]));

        $this->assertTrue(Schema::hasColumns('invoice_match_exceptions', [
            'invoice_match_result_id',
            'exception_code',
            'exception_status',
            'severity',
        ]));
    }

    public function test_treasury_foundation_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('bank_accounts'));
        $this->assertTrue(Schema::hasTable('bank_statements'));
        $this->assertTrue(Schema::hasTable('bank_statement_lines'));
        $this->assertTrue(Schema::hasTable('payment_runs'));
        $this->assertTrue(Schema::hasTable('payment_run_items'));
        $this->assertTrue(Schema::hasTable('reconciliation_matches'));
        $this->assertTrue(Schema::hasTable('reconciliation_exceptions'));

        $this->assertTrue(Schema::hasColumns('bank_statement_lines', [
            'bank_statement_id',
            'bank_account_id',
            'amount',
            'direction',
            'is_reconciled',
            'source_hash',
        ]));

        $this->assertTrue(Schema::hasColumns('payment_run_items', [
            'payment_run_id',
            'request_payout_execution_attempt_id',
            'vendor_invoice_payment_id',
            'expense_id',
            'item_status',
        ]));

        $this->assertTrue(Schema::hasColumns('reconciliation_matches', [
            'bank_statement_line_id',
            'match_target_type',
            'match_target_id',
            'match_stream',
            'match_status',
        ]));
    }
}
