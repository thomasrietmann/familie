<?php

namespace Tests\Feature;

use App\Models\CalendarMailReceipt;
use App\Models\FamilyEvent;
use App\Services\CalendarMail\ImapMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCalendarMail;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class CalendarMailPollingTest extends TestCase
{
    use CreatesCalendarMail, CreatesFamily, RefreshDatabase;

    private function configuredMailbox(string $identity = 'mailbox-123'): void
    {
        config(['calendar_mail.enabled' => true, 'calendar_mail.host' => 'mx.example.org', 'calendar_mail.password' => 'test-password']);
        $this->mock(ImapMailbox::class, function ($mock) use ($identity): void {
            $mock->shouldReceive('connect')->andReturn($identity);
            $mock->shouldReceive('uids')->andReturn([1]);
            $mock->shouldReceive('read')->with(1)->andReturn($this->mail($this->calendar()));
            $mock->shouldReceive('disconnect');
        });
    }

    public function test_polling_is_idempotent_even_after_uidvalidity_changes(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->configuredMailbox();
        $this->artisan('calendar-mail:poll')->assertSuccessful();
        $this->artisan('calendar-mail:poll')->assertSuccessful();
        $this->configuredMailbox('new-uidvalidity');
        $this->artisan('calendar-mail:poll')->assertSuccessful();
        $this->assertDatabaseCount('family_events', 1);
        $this->assertDatabaseCount('calendar_mail_receipts', 2);
        Http::assertSentCount(1);
    }

    public function test_transient_failure_retries_with_backoff_then_imports_once(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        Http::swap(new Factory);
        Http::fake(['api.openai.com/*' => Http::sequence()->push([], 503)->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"person":"family"}']]]]])]);
        $this->configuredMailbox();
        $this->artisan('calendar-mail:poll')->assertFailed();
        $this->assertSame('pending', CalendarMailReceipt::sole()->status);
        $this->artisan('calendar-mail:poll')->assertSuccessful();
        Http::assertSentCount(1);
        $this->travel(6)->minutes();
        $this->artisan('calendar-mail:poll')->assertSuccessful();
        $this->assertSame('processed', CalendarMailReceipt::sole()->status);
        $this->assertSame(2, CalendarMailReceipt::sole()->attempts);
        $this->assertSame('family', FamilyEvent::sole()->owner_type);
    }

    public function test_overlapping_runs_do_not_touch_mailbox_and_missing_trust_fails_closed(): void
    {
        $this->fakeAssignment();
        config(['calendar_mail.enabled' => true, 'calendar_mail.host' => 'mx.example.org', 'calendar_mail.password' => 'test']);
        $this->mock(ImapMailbox::class, fn ($mock) => $mock->shouldNotReceive('connect'));
        $lock = Cache::lock('calendar-mail:poll', 3600);
        $lock->get();
        $this->artisan('calendar-mail:poll')->expectsOutput('Ein Postfachabruf läuft bereits.')->assertSuccessful();
        $lock->release();
        config(['calendar_mail.trusted_authserv_ids' => []]);
        $this->artisan('calendar-mail:poll')->assertFailed();
    }

    public function test_retention_keeps_event_identity_tombstones(): void
    {
        $this->senderFor($this->familyFor());
        $this->fakeAssignment();
        $this->configuredMailbox();
        $this->artisan('calendar-mail:poll')->assertSuccessful();
        $this->travel(91)->days();
        $this->artisan('calendar-mail:prune')->assertSuccessful();
        $this->assertDatabaseCount('calendar_mail_receipts', 0);
        $this->assertDatabaseCount('calendar_mail_events', 1);
        $this->assertDatabaseCount('family_events', 1);
    }
}
