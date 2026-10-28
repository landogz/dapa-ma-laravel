<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (legacy HTTP API)
    |--------------------------------------------------------------------------
    |
    | Use the Cloud Messaging "Server key" from Firebase Console → Project
    | settings → Cloud Messaging (not a service-account email/JSON client_email).
    |
    */
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nominatim (OpenStreetMap) — MapLibre address suggestions
    |--------------------------------------------------------------------------
    |
    | Used by address autocomplete and rehab-center map pins. Identify this
    | app in User-Agent per Nominatim usage policy.
    |
    */
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'countrycodes' => env('NOMINATIM_COUNTRYCODES', 'ph'),
        'user_agent' => env(
            'NOMINATIM_USER_AGENT',
            'DAPE-MA/1.0 ('.rtrim((string) env('APP_URL', 'https://dapemade.ddb.gov.ph'), '/').'; contact@ddb.gov.ph)',
        ),
    ],

];
