<?php

return [
    'defaults' => [
        // Default statuses that can start procurement flow until tenant overrides are saved.
        'conversion_allowed_statuses' => ['approved'],
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
    ],
];