<?php

return [
    'defaults' => [
        // Default statuses that can start procurement flow until tenant overrides are saved.
        'conversion_allowed_statuses' => ['approved'],
        'require_vendor_on_conversion' => true,
        'default_expected_delivery_days' => 14,
        'auto_post_commitment_on_issue' => true,
        'issue_allowed_roles' => ['owner', 'finance'],
    ],
];
