<?php

namespace Tests\Concerns;

use App\Models\CalendarMailReceipt;
use App\Models\CalendarSenderAddress;
use App\Models\Family;
use Illuminate\Support\Facades\Http;

trait CreatesCalendarMail
{
    protected function senderFor(Family $family, string $email = 'parent@example.org'): CalendarSenderAddress
    {
        return CalendarSenderAddress::create(['family_id' => $family->id, 'user_id' => $family->owner_user_id, 'email' => $email, 'verified_at' => now(), 'consented_at' => now()]);
    }

    protected function calendar(array $changes = [], array $extraEvents = []): string
    {
        $properties = array_replace([
            'UID' => 'meeting-123@example.org', 'DTSTAMP' => '20261108T120000Z', 'SEQUENCE' => '0',
            'ORGANIZER' => 'mailto:parent@example.org', 'DTSTART' => '20261110T090000Z', 'DTEND' => '20261110T100000Z',
            'SUMMARY' => 'Training für Mia', 'DESCRIPTION' => 'Mia geht zum Training.',
        ], $changes);
        $method = $properties['METHOD'] ?? 'REQUEST';
        unset($properties['METHOD']);
        $events = '';
        foreach ([$properties, ...$extraEvents] as $event) {
            $events .= "BEGIN:VEVENT\r\n";
            foreach ($event as $key => $value) {
                if ($value !== null) {
                    $events .= $key.':'.$value."\r\n";
                }
            }
            $events .= "END:VEVENT\r\n";
        }

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//FamilyManager tests//DE\r\nMETHOD:$method\r\n".$events."END:VCALENDAR\r\n";
    }

    protected function mail(string $ics, string $sender = 'parent@example.org', string $authentication = 'mx.example.org; dmarc=pass header.from=example.org'): string
    {
        return "From: Parent <$sender>\r\nTo: familie@aufbollen.ch\r\nAuthentication-Results: $authentication\r\nSubject: Training Mia\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=mailboundary\r\n\r\n--mailboundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nTraining für Mia\r\n--mailboundary\r\nContent-Type: text/calendar; charset=UTF-8; method=REQUEST\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($ics))."--mailboundary--\r\n";
    }

    protected function receipt(): CalendarMailReceipt
    {
        return CalendarMailReceipt::create(['delivery_key' => hash('sha256', random_bytes(32))]);
    }

    protected function fakeAssignment(string $person = 'family'): void
    {
        config(['services.openai.api_key' => 'test-key', 'calendar_mail.trusted_authserv_ids' => ['mx.example.org']]);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['person' => $person])]]]]])]);
    }
}
