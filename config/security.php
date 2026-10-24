<?php

return [
    'force_https' => env('FORCE_HTTPS', true),
    'registration_enabled' => env('REGISTRATION_ENABLED', false),
    'audit_retention_days' => (int) env('AUDIT_RETENTION_DAYS', 180),
];
