<?php

namespace Tests\Feature;

use App\Mail\VerifyCalendarSender;
use App\Models\CalendarSenderAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesCalendarMail;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class CalendarSenderTest extends TestCase
{
    use CreatesCalendarMail, CreatesFamily, RefreshDatabase;

    public function test_address_requires_password_consent_and_ownership_confirmation(): void
    {
        Mail::fake();
        $family = $this->familyFor();
        $this->actingAs($family->owner);
        $this->get(route('calendar-mail.index'))->assertOk()->assertSee('familie@aufbollen.ch');
        $this->post(route('calendar-mail.store'), ['email' => 'parent@example.org'])->assertSessionHasErrors(['current_password', 'consent']);
        $this->post(route('calendar-mail.store'), ['email' => ' Parent@Example.ORG ', 'current_password' => 'password', 'consent' => '1'])->assertSessionHasNoErrors();
        $address = CalendarSenderAddress::sole();
        $this->assertSame('parent@example.org', $address->email);
        $this->assertNull($address->verified_at);
        $code = null;
        Mail::assertSent(VerifyCalendarSender::class, function ($mail) use (&$code): bool {
            $code = $mail->code;

            return $mail->hasTo('parent@example.org');
        });
        $this->assertNotSame($code, $address->verification_hash);
        $this->assertArrayNotHasKey('verification_hash', $address->toArray());
        $this->post(route('calendar-mail.verify', $address), ['code' => $code])->assertSessionHasNoErrors();
        $this->assertNotNull($address->fresh()->verified_at);
        $this->assertNull($address->fresh()->verification_hash);
        $this->post(route('calendar-mail.verify', $address), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_other_users_cannot_manage_addresses_or_read_receipts(): void
    {
        $family = $this->familyFor();
        $address = $this->senderFor($family);
        $otherFamily = $this->familyFor();
        $this->actingAs($otherFamily->owner)->get(route('calendar-mail.index'))->assertDontSee($address->email);
        $this->post(route('calendar-mail.verify', $address), ['code' => '12345678'])->assertNotFound();
        $this->post(route('calendar-mail.resend', $address))->assertNotFound();
        $this->delete(route('calendar-mail.destroy', $address))->assertNotFound();
        $this->actingAs($family->owner)->delete(route('calendar-mail.destroy', $address))->assertRedirect();
        $this->assertModelMissing($address);
    }

    public function test_codes_expire_and_lock_after_five_wrong_attempts_and_are_not_flashed(): void
    {
        $family = $this->familyFor();
        $address = $this->senderFor($family);
        $address->update(['verified_at' => null, 'verification_expires_at' => now()->addMinutes(30), 'verification_hash' => hash_hmac('sha256', '12345678', config('app.key'))]);
        $this->actingAs($family->owner);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('calendar-mail.verify', $address), ['code' => '00000000'])->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
        }
        $this->post(route('calendar-mail.verify', $address), ['code' => '12345678'])->assertSessionHasErrors('code');
        $this->assertNull($address->fresh()->verified_at);
        $address->update(['verification_attempts' => 0, 'verification_expires_at' => now()->subMinute()]);
        $this->post(route('calendar-mail.verify', $address), ['code' => '12345678'])->assertSessionHasErrors('code');
    }

    public function test_duplicates_and_mailbox_itself_are_rejected_and_log_mailer_is_never_used(): void
    {
        Mail::fake();
        $family = $this->familyFor();
        $this->senderFor($family);
        $this->actingAs($family->owner);
        foreach (['PARENT@example.org', 'familie@aufbollen.ch'] as $email) {
            $this->post(route('calendar-mail.store'), ['email' => $email, 'current_password' => 'password', 'consent' => '1'])->assertSessionHasErrors('email');
        }
        config(['mail.default' => 'log']);
        $this->post(route('calendar-mail.store'), ['email' => 'new@example.org', 'current_password' => 'password', 'consent' => '1'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
        $this->assertNull(CalendarSenderAddress::where('email', 'new@example.org')->firstOrFail()->verified_at);
    }
}
