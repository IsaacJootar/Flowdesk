<?php

return [
    'defaults' => [
        'statement_import_max_rows' => 5000,
        'auto_match_date_window_days' => 3,
        'auto_match_amount_tolerance' => 0,
        'auto_match_min_confidence' => 75,
        'direct_expense_text_similarity_threshold' => 55,
        'exception_alert_age_hours' => 48,
        'out_of_pocket_requires_reimbursement_link' => true,
    ],
];
