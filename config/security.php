<?php

return [
    'public_frame_origins' => env('PUBLIC_FRAME_ORIGINS', ''),
    'force_https' => env('FORCE_HTTPS', true),
    'registration_enabled' => env('REGISTRATION_ENABLED', false),
    'audit_retention_days' => (int) env('AUDIT_RETENTION_DAYS', 180),
];
