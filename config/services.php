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

    'jsiaga' => [
        'device_token' => env('JSIAGA_DEVICE_TOKEN'),
        'offline_seconds' => (int) env('JSIAGA_OFFLINE_SECONDS', 15),
        'history_interval_seconds' => (int) env('JSIAGA_HISTORY_INTERVAL_SECONDS', 10),
        'retention_days' => (int) env('JSIAGA_RETENTION_DAYS', 7),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'timeout' => (int) env('GROQ_TIMEOUT', 10),
    ],

    'telegram' => [
        'enabled' => (bool) env('TELEGRAM_NOTIFICATIONS_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'statuses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TELEGRAM_NOTIFY_STATUSES', 'WARNING,DANGER,FLOOD,SAFE')),
        ))),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),
        'alert_cooldown_seconds' => (int) env('TELEGRAM_ALERT_COOLDOWN_SECONDS', 60),
    ],

    'ai_limits' => [
        'per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 10),
        'per_day' => (int) env('AI_REQUESTS_PER_DAY', 100),
    ],

];
