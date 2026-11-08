<?php

namespace Tests\Unit;

use App\Services\CalendarMail\InvitationParser;
use App\Services\CalendarMail\MessageParser;
use App\Services\CalendarMail\RejectedMail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCalendarMail;
use Tests\TestCase;

class CalendarMailParsingTest extends TestCase
{
    use CreatesCalendarMail;

    public function test_exchange_custom_timezone_preserves_winter_and_summer_times(): void
    {
        $ics = file_get_contents(__DIR__.'/../Fixtures/outlook-custom-timezone.ics');
        foreach (['20261121' => '2026-11-21', '20260721' => '2026-07-21'] as $date => $expected) {
            $result = (new InvitationParser)->parse(str_replace('20261121', (string) $date, $ics), 'parent@example.org');
            $this->assertSame($expected.' 11:00:00', $result['items'][0]['data']['starts_at']);
            $this->assertSame($expected.' 19:00:00', $result['items'][0]['data']['ends_at']);
        }
    }

    public function test_custom_timezone_with_different_rules_is_not_assumed_to_be_swiss(): void
    {
        $ics = file_get_contents(__DIR__.'/../Fixtures/outlook-custom-timezone.ics');
        $this->expectException(RejectedMail::class);
        (new InvitationParser)->parse(str_replace('BYMONTH=10', 'BYMONTH=11', $ics), 'parent@example.org');
    }

    #[DataProvider('untrustedAuthentication')]
    public function test_sender_authentication_fails_closed(string $authentication): void
    {
        config(['calendar_mail.trusted_authserv_ids' => ['mx.example.org']]);
        $parser = new MessageParser;
        $message = $parser->parse($this->mail($this->calendar(), authentication: $authentication));
        $this->expectException(RejectedMail::class);
        $this->expectExceptionMessage('authentication_failed');
        $parser->authenticate($message, $parser->sender($message));
    }

    public static function untrustedAuthentication(): array
    {
        return [
            ['evil.example.org; dmarc=pass header.from=example.org'],
            ['mx.example.org; spf=pass smtp.mailfrom=example.org'],
            ['mx.example.org; dmarc=fail header.from=example.org'],
            ['mx.example.org; dmarc=pass header.from=other.org'],
            ['mx.example.org; dmarc=fail reason="; dmarc=pass header.from=example.org"'],
            ['mx.example.org; dmarc=fail (; dmarc=pass header.from=example.org)'],
            ['mx.example.org; dmarc=pass header.from=example.org header.from=other.org'],
            ["mx.example.org; dmarc=pass header.from=example.org\r\nAuthentication-Results: mx.example.org; dmarc=fail header.from=example.org"],
            ['mx.example.org; dmarc=pass reason="header.from=example.org"'],
        ];
    }

    public function test_folded_authentication_and_encoded_mime_are_supported_without_html_execution(): void
    {
        config(['calendar_mail.trusted_authserv_ids' => ['mx.example.org']]);
        $parser = new MessageParser;
        $message = $parser->parse($this->mail($this->calendar(), authentication: "mx.example.org;\r\n dmarc=pass (policy passed) header.from=\"example.org\""));
        $parser->authenticate($message, $parser->sender($message));
        $this->assertSame($this->calendar(), $parser->invitation($message)['calendar']);
        $this->assertSame('Hallo Mia', MessageParser::plainText('<script>alert(1)</script><p>Hallo Mia</p>'));
    }

    public function test_multiple_from_headers_and_multiple_from_mailboxes_are_rejected(): void
    {
        $parser = new MessageParser;
        foreach (["From: parent@example.org\r\nFrom: attacker@example.org\r\n\r\n", "From: parent@example.org, attacker@example.org\r\n\r\n"] as $raw) {
            try {
                $parser->sender($parser->parse($raw));
                $this->fail('Ambiguous sender accepted');
            } catch (RejectedMail $e) {
                $this->assertSame('authentication_failed', $e->reason);
            }
        }
    }

    public function test_inline_calendar_and_ics_attachment_are_supported(): void
    {
        $parser = new MessageParser;
        $raw = "From: parent@example.org\r\nContent-Type: text/calendar; charset=UTF-8\r\n\r\n".$this->calendar();
        $this->assertSame($this->calendar(), $parser->invitation($parser->parse($raw))['calendar']);
        $raw = str_replace('text/calendar; charset=UTF-8; method=REQUEST', 'application/octet-stream; name="invite.ics"', $this->mail($this->calendar()));
        $this->assertSame($this->calendar(), $parser->invitation($parser->parse($raw))['calendar']);
    }

    public function test_all_day_exclusive_end_and_outlook_windows_timezone_are_preserved(): void
    {
        $parser = new InvitationParser;
        $allDay = $parser->parse($this->calendar(['DTSTART' => null, 'DTEND' => null, 'DTSTART;VALUE=DATE' => '20261110', 'DTEND;VALUE=DATE' => '20261112']), 'parent@example.org');
        $this->assertTrue($allDay['items'][0]['data']['all_day']);
        $this->assertSame('2026-11-12 00:00:00', $allDay['items'][0]['data']['ends_at']);
        $windows = $parser->parse($this->calendar(['DTSTART' => null, 'DTEND' => null, 'DTSTART;TZID=W. Europe Standard Time' => '20260710T090000', 'DTEND;TZID=W. Europe Standard Time' => '20260710T100000']), 'parent@example.org');
        $this->assertSame('2026-07-10 09:00:00', $windows['items'][0]['data']['starts_at']);
    }

    public function test_recurrence_respects_dst_and_exdates(): void
    {
        $parsed = (new InvitationParser)->parse($this->calendar([
            'DTSTART' => null, 'DTEND' => null, 'DTSTART;TZID=Europe/Zurich' => '20261020T090000', 'DTEND;TZID=Europe/Zurich' => '20261020T100000',
            'RRULE' => 'FREQ=WEEKLY;COUNT=3', 'EXDATE;TZID=Europe/Zurich' => '20261027T090000',
        ]), 'parent@example.org');
        $this->assertCount(2, $parsed['items']);
        $this->assertSame(['2026-10-20 09:00:00', '2026-11-03 09:00:00'], array_column(array_column($parsed['items'], 'data'), 'starts_at'));
    }

    public function test_weekday_list_and_monthly_last_weekday_are_supported(): void
    {
        foreach (['FREQ=WEEKLY;COUNT=3;BYDAY=TU,TH', 'FREQ=MONTHLY;COUNT=3;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1'] as $rule) {
            $parsed = (new InvitationParser)->parse($this->calendar(['RRULE' => $rule]), 'parent@example.org');
            $this->assertCount(3, $parsed['items']);
        }
    }

    #[DataProvider('invalidCalendars')]
    public function test_invalid_or_unbounded_calendar_data_is_rejected(array $changes): void
    {
        $this->expectException(RejectedMail::class);
        (new InvitationParser)->parse($this->calendar($changes), 'parent@example.org');
    }

    public static function invalidCalendars(): array
    {
        return [
            [['UID' => null]], [['DTSTART' => '20260230T090000Z']], [['DTEND' => '20261109T100000Z']],
            [['METHOD' => 'REPLY']], [['DTSTAMP' => null]], [['SEQUENCE' => '-1']],
            [['DTSTART' => null, 'DTSTART;TZID=Unknown/Evil' => '20261110T090000']],
            [['RRULE' => 'FREQ=WEEKLY']], [['RRULE' => 'FREQ=SECONDLY;COUNT=100']],
            [['RRULE' => 'FREQ=DAILY;COUNT=367']], [['RRULE' => 'FREQ=DAILY;COUNT=2;INTERVAL=0']],
            [['RRULE' => 'FREQ=YEARLY;COUNT=2;BYMONTH=13']],
            [['RRULE' => 'FREQ=MONTHLY;COUNT=2;BYDAY=6MO']],
            [['RRULE' => 'FREQ=DAILY;COUNT=2;INTERVAL=7;BYDAY=MO']],
            [['RECURRENCE-ID;RANGE=THISANDFUTURE' => '20261110T090000Z']],
        ];
    }
}
