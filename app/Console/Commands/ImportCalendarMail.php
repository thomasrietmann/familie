<?php

namespace App\Console\Commands;

use App\Models\CalendarMailReceipt;
use App\Services\CalendarMail\ImapMailbox;
use App\Services\CalendarMail\InvitationImporter;
use App\Services\CalendarMail\RejectedMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportCalendarMail extends Command
{
    protected $signature = 'calendar-mail:poll';

    protected $description = 'Authentifizierte Kalendereinladungen aus dem IMAP-Postfach importieren';

    public function handle(ImapMailbox $mailbox, InvitationImporter $importer): int
    {
        if (! config('calendar_mail.enabled')) {
            $this->info('E-Mail-Terminimport ist deaktiviert.');

            return self::SUCCESS;
        }
        if (! config('calendar_mail.host') || ! config('calendar_mail.password') || ! config('calendar_mail.trusted_authserv_ids') || ! config('services.openai.api_key')) {
            $this->error('E-Mail-Terminimport ist unvollständig konfiguriert.');

            return self::FAILURE;
        }
        // One poller per deployment, including direct cron invocations. Lease exceeds bounded batch runtime.
        $lock = Cache::lock('calendar-mail:poll', 3600);
        if (! $lock->get()) {
            $this->info('Ein Postfachabruf läuft bereits.');

            return self::SUCCESS;
        }
        $processed = 0;
        $failed = false;
        try {
            $mailboxKey = $mailbox->connect();
            foreach ($mailbox->uids() as $uid) {
                if ($processed >= config('calendar_mail.batch_size')) {
                    break;
                }
                $key = hash('sha256', $mailboxKey.'|'.$uid);
                $receipt = CalendarMailReceipt::firstOrCreate(['delivery_key' => $key]);
                if (in_array($receipt->status, ['processed', 'rejected', 'failed'], true) || $receipt->retry_at?->isFuture()) {
                    continue;
                }
                if ($receipt->attempts >= config('calendar_mail.max_attempts')) {
                    $receipt->update(['status' => 'failed', 'result_code' => 'temporary_failure', 'retry_at' => null]);
                    $failed = true;

                    continue;
                }
                $processed++;
                $receipt->increment('attempts');
                // A killed process leaves a pending receipt; the next run safely retries it.
                try {
                    $result = $importer->import($mailbox->read($uid), $receipt);
                    $receipt->update(['status' => 'processed', 'result_code' => $result, 'retry_at' => null]);
                } catch (RejectedMail $e) {
                    $receipt->update(['status' => 'rejected', 'result_code' => $e->reason, 'retry_at' => null]);
                } catch (Throwable) {
                    $failed = true;
                    $receipt->update([
                        'status' => $receipt->attempts >= config('calendar_mail.max_attempts') ? 'failed' : 'pending',
                        'result_code' => 'temporary_failure', 'retry_at' => now()->addMinutes(min(360, 5 ** $receipt->attempts)),
                    ]);
                    Log::warning('Calendar mail import deferred', ['receipt_id' => $receipt->id]);
                }
            }
            $this->info($processed.' Nachrichten verarbeitet.');

            return $failed ? self::FAILURE : self::SUCCESS;
        } catch (Throwable) {
            Log::warning('Calendar mailbox polling failed');
            $this->error('Postfachabruf fehlgeschlagen. Verbindung und Konfiguration prüfen.');

            return self::FAILURE;
        } finally {
            try {
                $mailbox->disconnect();
            } catch (Throwable) {
                // Never emit credentials or IMAP protocol responses from disconnect failures.
            }
            $lock->release();
        }
    }
}
