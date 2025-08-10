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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'shopify' => [
        'domain' => env('SHOPIFY_SHOP_DOMAIN'),
        'token' => env('SHOPIFY_ADMIN_TOKEN'),
        'version' => env('SHOPIFY_API_VERSION', '2025-07'),
        'mode' => env('SHOPIFY_MODE', 'proxy'), // proxy | local
        'sync' => [
            'driver' => env('SHOPIFY_SYNC_DRIVER', 'job'),
            'timeout' => (int)env('SHOPIFY_SYNC_TIMEOUT', 0),
        ]
    ],

];
