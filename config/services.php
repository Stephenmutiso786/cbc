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
    'subscription' => [
        'consumer_key'    => env('SUBSCRIPTION_MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('SUBSCRIPTION_MPESA_CONSUMER_SECRET'),
        'shortcode'       => env('SUBSCRIPTION_MPESA_SHORTCODE'),
        'passkey'         => env('SUBSCRIPTION_MPESA_PASSKEY'),
        'callback_url'    => env('SUBSCRIPTION_MPESA_CALLBACK_URL'),
    ],

    'catboost' => [
        'url' => env('CATBOOST_SERVICE_URL', 'http://127.0.0.1:8000/api/ml/predict-ticket'),
    ],

];
