<?php

namespace Tests\Feature;

use App\Models\CalendarMailEvent;
use App\Models\Child;
use App\Models\FamilyEvent;
use App\Services\CalendarMail\InvitationImporter;
use App\Services\CalendarMail\RejectedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCalendarMail;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class CalendarMailImportTest extends TestCase
{
    use CreatesCalendarMail, CreatesFamily, RefreshDatabase;

    public function test_verified_reply_to_routes_calendar_service_mail_when_authentication_is_disabled(): void
    {
        $family = $this->familyFor();
        $this->senderFor($family);
        $this->fakeAssignment();
        config(['calendar_mail.require_authentication' => false]);
        $raw = "Reply-To: parent@example.org\r\n".$this->mail($this->calendar(), 'calendar@provider.example');
        $this->assertSame('imported', app(InvitationImporter::class)->import($raw, $this->receipt()));
        $this->assertSame($family->id, FamilyEvent::sole()->family_id);
    }

    public function test_reply_to_cannot_cross_accounts(): void
    {
        $this->senderFor($this->familyFor());
        $this->senderFor($this->familyFor(), 'other@example.org');
        $this->fakeAssignment();
        config(['calendar_mail.require_authentication' => false]);
        $this->expectExceptionMessage('ambiguous_sender');
        app(InvitationImporter::class)->import("Reply-To: other@example.org\r\n".$this->mail($this->calendar()), $this->receipt());
    }

    public function test_dmarc_pass_alone_does_not_authorize_reply_to_routing(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $raw = "Reply-To: parent@example.org\r\n".$this->mail($this->calendar(), 'calendar@example.org');
        try {
            app(InvitationImporter::class)->import($raw, $this->receipt());
            $this->fail('Expected provider authorization requirement');
        } catch (RejectedMail $e) {
            $this->assertSame('authentication_failed', $e->reason);
        }
        Http::assertNothingSent();
        config(['calendar_mail.reply_to_senders' => ['calendar@example.org']]);
        $this->assertSame('imported', app(InvitationImporter::class)->import($raw, $this->receipt()));
    }

    public function test_unverified_and_multiple_reply_to_addresses_are_rejected(): void
    {
        $this->fakeAssignment();
        config(['calendar_mail.require_authentication' => false]);
        foreach (['unknown@example.org' => 'unknown_sender', 'one@example.org, two@example.org' => 'ambiguous_sender'] as $replyTo => $reason) {
            try {
                app(InvitationImporter::class)->import("Reply-To: $replyTo\r\n".$this->mail($this->calendar()), $this->receipt());
                $this->fail('Expected rejection');
            } catch (RejectedMail $e) {
                $this->assertSame($reason, $e->reason);
            }
        }
        Http::assertNothingSent();
    }

    public function test_invitation_is_parsed_deterministically_and_ai_assigns_only_family_members(): void
    {
        $family = $this->familyFor();
        $child = Child::factory()->create(['family_id' => $family->id, 'first_name' => 'Mia']);
        $this->senderFor($family);
        $this->fakeAssignment('child:'.$child->id);
        $result = $this->import($this->calendar());
        $this->assertSame('imported', $result);
        $event = FamilyEvent::sole();
        $this->assertSame('2026-11-10 10:00:00', $event->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('child', $event->owner_type);
        $this->assertSame($child->id, $event->owner_id);
        $this->assertSame('parents_only', $event->visibility);
        $this->assertSame('email', $event->source);
        Http::assertSent(fn ($request) => $request['store'] === false && $request['text']['format']['strict'] === true && ! str_contains($request['input'], 'parent@example.org'));
    }

    public function test_duplicate_older_update_and_cancellation_do_not_duplicate_events_or_call_ai(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->import($this->calendar());
        $this->assertSame('unchanged', $this->import($this->calendar()));
        $this->import($this->calendar(['SEQUENCE' => '2', 'DTSTART' => '20261110T110000Z', 'DTEND' => '20261110T120000Z']));
        $this->assertSame('unchanged', $this->import($this->calendar(['SEQUENCE' => '1'])));
        $this->assertSame('cancelled', $this->import($this->calendar(['METHOD' => 'CANCEL', 'SEQUENCE' => '3', 'DTSTART' => null, 'DTEND' => null])));
        $this->assertSame('unchanged', $this->import($this->calendar(['SEQUENCE' => '2'])));
        $this->assertSame('cancelled', FamilyEvent::sole()->status);
        $this->assertSame('12:00', FamilyEvent::sole()->starts_at->format('H:i'));
        Http::assertSentCount(2);
    }

    public function test_no_match_and_foreign_person_ids_become_family_events(): void
    {
        $family = $this->familyFor();
        $foreign = Child::factory()->create(['family_id' => $this->familyFor()->id]);
        $this->senderFor($family);
        $this->fakeAssignment('child:'.$foreign->id);
        $this->import($this->calendar());
        $this->assertSame('family', FamilyEvent::sole()->owner_type);
        $this->assertNull(FamilyEvent::sole()->owner_id);
    }

    public function test_cancel_before_request_is_a_tombstone_and_manual_deletions_stay_deleted(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->import($this->calendar(['METHOD' => 'CANCEL', 'SEQUENCE' => '2']));
        $this->import($this->calendar());
        $this->assertDatabaseCount('family_events', 0);
        $this->import($this->calendar(['UID' => 'second']));
        FamilyEvent::sole()->delete();
        $this->import($this->calendar(['UID' => 'second', 'SEQUENCE' => '1']));
        $this->assertDatabaseCount('family_events', 0);
    }

    public function test_unverified_revoked_inactive_and_unknown_senders_never_reach_openai(): void
    {
        $family = $this->familyFor();
        $sender = $this->senderFor($family);
        $this->fakeAssignment();
        $sender->update(['verified_at' => null]);
        $this->assertRejected('unknown_sender');
        $sender->update(['verified_at' => now()]);
        $family->users()->updateExistingPivot($sender->user_id, ['status' => 'inactive']);
        $this->assertRejected('unknown_sender');
        $sender->delete();
        $this->assertRejected('unknown_sender');
        Http::assertNothingSent();
    }

    public function test_finite_series_update_exception_and_cancellation(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->import($this->calendar(['RRULE' => 'FREQ=WEEKLY;COUNT=3']));
        $this->assertDatabaseCount('family_events', 3);
        $this->assertDatabaseCount('calendar_mail_events', 4);
        Http::assertSentCount(1);
        $this->assertSame('unchanged', $this->import($this->calendar(['RRULE' => 'FREQ=WEEKLY;COUNT=3'])));
        $this->import($this->calendar(['RECURRENCE-ID' => '20261117T090000Z', 'DTSTART' => '20261117T110000Z', 'DTEND' => '20261117T120000Z', 'SEQUENCE' => '1']));
        $this->assertDatabaseHas('family_events', ['starts_at' => '2026-11-17 12:00:00']);
        $this->import($this->calendar(['METHOD' => 'CANCEL', 'SEQUENCE' => '2', 'DTSTART' => null, 'DTEND' => null]));
        $this->assertSame(3, FamilyEvent::where('status', 'cancelled')->count());
    }

    public function test_failed_ai_is_retryable_and_does_not_create_partial_events(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        Http::swap(new Factory);
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'private-provider-error'], 503)]);
        try {
            $this->import($this->calendar());
            $this->fail('Expected retryable failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Calendar assignment unavailable', $e->getMessage());
        }
        $this->assertDatabaseCount('family_events', 0);
        $this->assertDatabaseCount('calendar_mail_events', 0);
    }

    public function test_series_revision_removes_instances_and_can_reinstate_a_cancelled_series(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->import($this->calendar(['RRULE' => 'FREQ=WEEKLY;COUNT=3']));
        $this->import($this->calendar(['RRULE' => 'FREQ=WEEKLY;COUNT=2', 'SEQUENCE' => '1', 'SUMMARY' => 'Neuer Titel']));
        $this->assertSame(2, FamilyEvent::where('status', 'confirmed')->where('title', 'Neuer Titel')->count());
        $this->assertSame(1, FamilyEvent::where('status', 'cancelled')->count());
        $this->import($this->calendar(['METHOD' => 'CANCEL', 'SEQUENCE' => '2']));
        $this->assertSame(3, FamilyEvent::where('status', 'cancelled')->count());
        $this->import($this->calendar(['RRULE' => 'FREQ=WEEKLY;COUNT=2', 'SEQUENCE' => '3']));
        $this->assertSame(2, FamilyEvent::where('status', 'confirmed')->count());
        $this->assertDatabaseCount('family_events', 3);
    }

    public function test_revision_order_is_utc_during_repeated_dst_hour(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->import($this->calendar(['DTSTAMP' => '20261025T005000Z']));
        $this->import($this->calendar(['DTSTAMP' => '20261025T011000Z', 'SUMMARY' => 'Aktualisiert nach Zeitumstellung']));
        $this->assertSame('Aktualisiert nach Zeitumstellung', FamilyEvent::sole()->title);
    }

    public function test_revocation_during_ai_request_prevents_import(): void
    {
        $sender = $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        Http::swap(new Factory);
        Http::fake(['api.openai.com/*' => function () use ($sender) {
            $sender->delete();

            return Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"person":"family"}']]]]]);
        }]);
        $this->assertRejected('unknown_sender');
        $this->assertDatabaseCount('family_events', 0);
    }

    public function test_same_invitation_is_isolated_between_families(): void
    {
        $family = $this->familyFor();
        $other = $this->familyFor();
        $this->senderFor($family);
        $this->senderFor($other, 'other@example.org');
        $this->fakeAssignment();
        $this->import($this->calendar());
        app(InvitationImporter::class)->import($this->mail($this->calendar(), 'other@example.org'), $this->receipt());
        $this->assertDatabaseCount('family_events', 2);
        $this->assertSame(2, CalendarMailEvent::distinct()->count('family_id'));
    }

    private function import(string $ics): string
    {
        return app(InvitationImporter::class)->import($this->mail($ics), $this->receipt());
    }

    private function assertRejected(string $reason): void
    {
        try {
            $this->import($this->calendar());
            $this->fail('Expected rejection');
        } catch (RejectedMail $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
