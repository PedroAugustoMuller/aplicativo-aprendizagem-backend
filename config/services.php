<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // "Gerar com IA". Empty provider = feature off (route answers
    // content.suggestions.disabled, /auth/me says ai_suggestions: false).
    'ai' => [
        'provider' => env('AI_PROVIDER', ''),
        'limits' => [
            'per_user_hour' => (int) env('AI_LIMIT_PER_USER_HOUR', 10),
            'per_user_day' => (int) env('AI_LIMIT_PER_USER_DAY', 30),
            'global_day' => (int) env('AI_LIMIT_GLOBAL_DAY', 200),
        ],
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        // Under nginx's 60 s fastcgi_read_timeout and PHP's 60 s max_execution_time.
        'timeout' => 45,
    ],

];
