<?php

return [
    'enabled' => (bool) env('CALENDAR_MAIL_ENABLED', false),
    'require_authentication' => (bool) env('CALENDAR_MAIL_REQUIRE_AUTHENTICATION', true),
    'address' => env('CALENDAR_MAIL_ADDRESS', 'familie@aufbollen.ch'),
    'host' => env('CALENDAR_MAIL_HOST'),
    'port' => (int) env('CALENDAR_MAIL_PORT', 993),
    'username' => env('CALENDAR_MAIL_USERNAME', 'familie@aufbollen.ch'),
    'password' => env('CALENDAR_MAIL_PASSWORD'),
    'folder' => env('CALENDAR_MAIL_FOLDER', 'INBOX'),
    // Enable only after the provider confirms that forged local Authentication-Results are stripped.
    'trusted_authserv_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('CALENDAR_MAIL_TRUSTED_AUTHSERV_IDS', ''))))),
    'max_bytes' => 2 * 1024 * 1024,
    'batch_size' => 10,
    'lookback_days' => 30,
    'max_attempts' => 5,
];
