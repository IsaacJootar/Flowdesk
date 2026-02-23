<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Request Approval SLA Defaults
    |--------------------------------------------------------------------------
    |
    | step_due_hours: hard deadline for one approval step.
    | reminder_hours_before_due: when to notify pending approvers before due_at.
    | escalation_grace_hours: how long after due_at before escalation.
    |
    */
    'request_sla' => [
        'step_due_hours' => (int) env('REQUEST_STEP_DUE_HOURS', 24),
        'reminder_hours_before_due' => (int) env('REQUEST_REMINDER_HOURS_BEFORE_DUE', 6),
        'escalation_grace_hours' => (int) env('REQUEST_ESCALATION_GRACE_HOURS', 6),
    ],
];
