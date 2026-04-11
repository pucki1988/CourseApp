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

    'google_wallet' => [
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'fitness_class_id' => env('GOOGLE_WALLET_FITNESS_CLASS_ID'),
        'fitness_class_suffix' => env('GOOGLE_WALLET_FITNESS_CLASS_SUFFIX', 'fitnesspass'),
        'member_class_id' => env('GOOGLE_WALLET_MEMBER_CLASS_ID'),
        'member_class_suffix' => env('GOOGLE_WALLET_MEMBER_CLASS_SUFFIX', 'memberpass'),
        'issuer_name' => env('GOOGLE_WALLET_ISSUER_NAME', env('APP_NAME', 'CourseApp')),
        'service_account_email' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_EMAIL'),
        'private_key' => env('GOOGLE_WALLET_PRIVATE_KEY'),
        'origin' => env('GOOGLE_WALLET_ORIGIN', env('APP_URL')),
    ],

];
