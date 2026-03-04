<?php

return [
    'defaults' => [
        // Default statuses that can start procurement flow until tenant overrides are saved.
        'conversion_allowed_statuses' => ['approved', 'approved_for_execution'],
        'require_vendor_on_conversion' => true,
        'default_expected_delivery_days' => 14,
        'auto_post_commitment_on_issue' => true,
        'issue_allowed_roles' => ['owner', 'finance'],
        // Receiving inventory is operationally sensitive, so it uses explicit role control.
        'receipt_allowed_roles' => ['owner', 'finance', 'manager'],
        // Linking invoices to POs is a financial-control action; keep default scope tighter.
        'invoice_link_allowed_roles' => ['owner', 'finance'],
        // Disabled by default to prevent quantity drift without deliberate tenant opt-in.
        'allow_over_receipt' => false,
        // Match tolerances keep policy tenant-configurable and avoid hidden hardcoded control thresholds.
        'match_amount_tolerance_percent' => 2,
        'match_quantity_tolerance_percent' => 0,
        'match_date_tolerance_days' => 7,
        'block_payment_on_mismatch' => true,
        'match_override_allowed_roles' => ['owner', 'finance'],
        // Governance hardening: when enabled, non-PO payout/expense paths are blocked by policy.
        'mandatory_po_enabled' => false,
        'mandatory_po_min_amount' => 0,
        'mandatory_po_category_codes' => [],
        // Maker-checker keeps the same person from both creating and overriding a match exception.
        'match_override_requires_maker_checker' => true,
        // Alert stale active commitments so long-open obligations do not silently age.
        'stale_commitment_alert_age_hours' => 72,
        'stale_commitment_alert_count_threshold' => 3,
    ],


    'backfill' => [
        // Conservative defaults reduce false links when migrating legacy invoice/payment records.
        'batch_size' => 200,
        'invoice_date_window_days' => 60,
        'amount_tolerance_percent' => 5,
    ],
];


