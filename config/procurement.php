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
    ],
];