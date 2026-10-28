<?php

return [

    'show_demo_login' => env('DAPE_SHOW_DEMO_LOGIN', env('APP_DEBUG', false)),

    'mobile' => [
        'version' => env('MOBILE_APP_VERSION', '1.0.1'),
        'build' => (int) env('MOBILE_APP_BUILD', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo login accounts (admin login page shortcuts)
    |--------------------------------------------------------------------------
    |
    | Shown when DAPE_SHOW_DEMO_LOGIN=true (defaults to APP_DEBUG). Password must match seeder.
    |
    */

    'demo_login_accounts' => [
        [
            'label' => 'Super Admin',
            'email' => 'superadmin@yopmail.com',
            'password' => 'password',
            'role' => 'super_admin',
        ],
        [
            'label' => 'Editor',
            'email' => 'editor@yopmail.com',
            'password' => 'password',
            'role' => 'editor',
        ],
        [
            'label' => 'Publisher',
            'email' => 'publisher@yopmail.com',
            'password' => 'password',
            'role' => 'publisher',
        ],
        [
            'label' => 'Analytics Viewer',
            'email' => 'analytics@yopmail.com',
            'password' => 'password',
            'role' => 'analytics_viewer',
        ],
    ],

];
