<?php

use App\Models\AuditLog;
use App\Models\CalendarMailReceipt;
use App\Models\CalendarSenderAddress;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('familymanager:about', function (): void {
    $this->info('FamilyManager');
})->purpose('Show FamilyManager application info');

Artisan::command('audit:prune', function (): void {
    $days = max(30, (int) config('security.audit_retention_days', 180));
    $count = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    $this->info($count.' alte Protokolleinträge gelöscht.');
})->purpose('Alte Audit-Einträge nach der konfigurierten Frist löschen');

Schedule::command('calendar-mail:poll')->everyFiveMinutes()->withoutOverlapping(60);

Artisan::command('calendar-mail:prune', function (): void {
    // Delivery receipts outlive the 30-day intake window; event identity tombstones remain with the family.
    CalendarMailReceipt::where('created_at', '<', now()->subDays(90))->delete();
    CalendarSenderAddress::whereNull('verified_at')->where('updated_at', '<', now()->subDay())->delete();
    $this->info('Alte Mailnachweise und unbestätigte Absenderadressen gelöscht.');
})->purpose('Datensparsame Aufbewahrung der E-Mail-Importnachweise');

Schedule::command('calendar-mail:prune')->daily()->withoutOverlapping();
