<?php

use App\Models\AuditLog;
use Illuminate\Support\Facades\Artisan;

Artisan::command('familymanager:about', function (): void {
    $this->info('FamilyManager');
})->purpose('Show FamilyManager application info');

Artisan::command('audit:prune', function (): void {
    $days = max(30, (int) config('security.audit_retention_days', 180));
    $count = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    $this->info($count.' alte Protokolleinträge gelöscht.');
})->purpose('Alte Audit-Einträge nach der konfigurierten Frist löschen');
