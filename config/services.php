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

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID'),
    ],

    'twilio' => [
        // Support both common env names
        'sid' => env('TWILIO_SID', env('TWILIO_ACCOUNT_SID')),
        'token' => env('TWILIO_TOKEN', env('TWILIO_AUTH_TOKEN')),
        // E.g. 'whatsapp:+14155238886' or raw number; we'll normalize in code
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', env('SANDBOX_PHONE_NUMBER')),
    ],

    'wise' => [
        'token' => env('WISE_TOKEN'),
        'profile_id' => env('WISE_PROFILE_ID'),
        'environment' => env('WISE_ENVIRONMENT', 'sandbox'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

];
