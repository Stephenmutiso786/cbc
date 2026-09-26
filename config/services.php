<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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

    'mpesa' => [
        'env'             => env('MPESA_ENV', 'sandbox'),
        'consumer_key'    => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode'       => env('MPESA_SHORTCODE'),
        'passkey'         => env('MPESA_PASSKEY'),
        'callback_url'    => env('MPESA_CALLBACK_URL'),
        'confirmation_url'=> env('MPESA_CONFIRMATION_URL'),
        'validation_url'  => env('MPESA_VALIDATION_URL'),
    ],

    // Platform subscription billing. These credentials belong to ElimuHub,
    // not to an individual school’s parent-fee collection account.
    'platform_mpesa' => [
        'env'             => env('PLATFORM_MPESA_ENV', 'sandbox'),
        'consumer_key'    => env('PLATFORM_MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('PLATFORM_MPESA_CONSUMER_SECRET'),
        'shortcode'       => env('PLATFORM_MPESA_SHORTCODE'),
        'passkey'         => env('PLATFORM_MPESA_PASSKEY'),
        'callback_url'    => env('PLATFORM_MPESA_CALLBACK_URL'),
        'sms_unit_price'  => env('PLATFORM_SMS_UNIT_PRICE', 1),
    ],

    'olympus_sms' => [
        'api_url' => env('OLYMPUS_SMS_API_URL', 'https://sms.ots.co.ke'),
        'portal_url' => env('OLYMPUS_SMS_PORTAL_URL', 'https://sms.ots.co.ke/login'),
        'api_token' => env('OLYMPUS_SMS_API_TOKEN'),
        'sender_id' => env('OLYMPUS_SMS_SENDER_ID', 'SCHOOL'),
    ],

    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'miyagi_labs' => [
        'url' => env('MIYAGI_LABS_URL', 'https://miyagilabs.ai'),
    ],

    'risk_prediction' => [
        'python_binary' => env('CATBOOST_PYTHON_BINARY', 'python3'),
        'timeout' => (int) env('CATBOOST_LOCAL_TIMEOUT', 120),
    ],

    'kemis' => [
        'api_url'     => env('KEMIS_API_URL', 'https://kemis.education.go.ke/api'),
        'api_key'     => env('KEMIS_API_KEY'),
        'school_code' => env('KEMIS_SCHOOL_CODE'),
    ],

    'google_drive' => [
        'enabled' => filter_var(env('GOOGLE_DRIVE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'credentials' => env('GOOGLE_DRIVE_CREDENTIALS'),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'oauth_token' => env('GOOGLE_DRIVE_OAUTH_TOKEN'),
        'report_backup' => filter_var(env('GOOGLE_DRIVE_REPORT_BACKUP', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
