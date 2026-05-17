<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.4'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
    ],
];
