<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_CHAT_ENABLED', true),
    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 30),
    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 10),
    'auto_title' => (bool) env('AI_CHAT_AUTO_TITLE', true),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://googleapis.com'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],
];
