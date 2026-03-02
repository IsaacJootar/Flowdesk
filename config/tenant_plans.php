<?php

return [
    'default_plan' => 'pilot',

    /*
    |--------------------------------------------------------------------------
    | Plan Policy Matrix
    |--------------------------------------------------------------------------
    |
    | This is the platform-level default module matrix per plan. Tenant owners
    | can still receive explicit entitlement overrides from platform operators.
    |
    */
    'plans' => [
        'pilot' => [
            'label' => 'Pilot',
            'default_seat_limit' => null,
            'entitlements' => [
                'requests' => true,
                'expenses' => true,
                'vendors' => false,
                'budgets' => true,
                'assets' => false,
                'reports' => true,
                'communications' => false,
                'ai' => false,
                'fintech' => false,
                'procurement' => false,
                'treasury' => false,
            ],
        ],
        'growth' => [
            'label' => 'Growth',
            'default_seat_limit' => null,
            'entitlements' => [
                'requests' => true,
                'expenses' => true,
                'vendors' => true,
                'budgets' => true,
                'assets' => false,
                'reports' => true,
                'communications' => true,
                'ai' => false,
                'fintech' => false,
                'procurement' => false,
                'treasury' => false,
            ],
        ],
        'business' => [
            'label' => 'Business',
            'default_seat_limit' => null,
            'entitlements' => [
                'requests' => true,
                'expenses' => true,
                'vendors' => true,
                'budgets' => true,
                'assets' => true,
                'reports' => true,
                'communications' => true,
                'ai' => false,
                'fintech' => false,
                'procurement' => false,
                'treasury' => false,
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'default_seat_limit' => null,
            'entitlements' => [
                'requests' => true,
                'expenses' => true,
                'vendors' => true,
                'budgets' => true,
                'assets' => true,
                'reports' => true,
                'communications' => true,
                'ai' => false,
                'fintech' => false,
                'procurement' => false,
                'treasury' => false,
            ],
        ],
    ],
];
