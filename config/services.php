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

    'firebase' =>[
        'credentials'=> base_path('storage/app/firebase/futsalmateapp-firebase-adminsdk-fbsvc-2ad0296d89.json'),
    ],

    'esewa' => [
        'merchant_code' => env('ESEWA_MERCHANT_CODE', 'EPAYTEST'),
        'secret_key' => env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'),
        'hold_minutes' => (int) env('ESEWA_HOLD_MINUTES', 10),
        // ePay v2 form POST target (not the transaction/status URL).
        // Test: https://developer.esewa.com.np/pages/Epay-V2
        'payment_url' => env(
            'ESEWA_PAYMENT_URL',
            'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
        ),
        'environment' => env('ESEWA_ENVIRONMENT', 'test'),
        /*
        | Optional override for eSewa success/failure URLs (no trailing slash, no /api).
        | If unset, the URL is taken from the current HTTP request (same host as POST /api/book).
        */
        'callback_base_url' => env('APP_URL_PUBLIC')
            ? rtrim((string) env('APP_URL_PUBLIC'), '/')
            : null,
    ],

];
