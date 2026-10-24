<?php

return [
    'recaptcha' => [
        'enabled' => env('RECAPTCHA_ENABLED', env('APP_ENV') === 'production'),
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'expected_hostname' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST),
        'minimum_score' => 0.5,
        'timeout_seconds' => 4,
        'maximum_age_seconds' => 120,
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.4'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],
];
