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

    'apple_wallet' => [
        // Pass Type Identifier from Apple Developer Portal (e.g. pass.de.djk-sg-schoenbrunn.fitness)
        'pass_type_identifier' => env('APPLE_WALLET_PASS_TYPE_IDENTIFIER'),
        // 10-character Team ID from Apple Developer Portal
        'team_identifier' => env('APPLE_WALLET_TEAM_IDENTIFIER'),
        'organization_name' => env('APPLE_WALLET_ORGANIZATION_NAME', env('APP_NAME', 'CourseApp')),
        // Either provide a path to the .p12 certificate file OR its base64-encoded content
        'certificate_path' => env('APPLE_WALLET_CERTIFICATE_PATH'),
        'certificate_content' => env('APPLE_WALLET_CERTIFICATE_CONTENT'), // base64-encoded .p12
        'certificate_password' => env('APPLE_WALLET_CERTIFICATE_PASSWORD', ''),
        'web_service_url' => env('APPLE_WALLET_WEB_SERVICE_URL', rtrim(env('APP_URL'), '/').'/api/wallet/apple'),
        'auth_token_salt' => env('APPLE_WALLET_AUTH_TOKEN_SALT'),
    ],

];
