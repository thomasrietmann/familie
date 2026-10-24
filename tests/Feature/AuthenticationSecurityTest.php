<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesFamily;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use CreatesFamily, RefreshDatabase;

    public function test_valid_login_normalizes_email_and_regenerates_session(): void
    {
        $user = User::factory()->create(['email' => 'parent@example.org']);
        $this->withSession(['marker' => 'retained']);
        $previous = session()->getId();
        $this->post('/login', ['email' => ' PARENT@example.org ', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previous, session()->getId());
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $user->id]);
    }

    public function test_five_failed_attempts_block_correct_password_without_logging_credentials(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'Secret-wrong-password'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $logs = AuditLog::all()->toJson();
        $this->assertStringNotContainsString($user->email, $logs);
        $this->assertStringNotContainsString('Secret-wrong-password', $logs);
        $this->travel(16)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_login_also_limits_an_ip_across_different_accounts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => 'guess'.$i.'@example.org', 'password' => 'bad']);
        }
        $this->post('/login', ['email' => 'next@example.org', 'password' => 'bad'])->assertTooManyRequests();
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['private' => 'content'])
            ->post('/logout')->assertRedirect('/login')->assertSessionMissing('private');
        $this->assertGuest();
    }

    public function test_registration_is_closed_unless_explicitly_enabled(): void
    {
        config(['security.registration_enabled' => false]);
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_validates_password_and_creates_exactly_one_family(): void
    {
        config(['security.registration_enabled' => true]);
        $data = ['name' => 'Elternteil', 'email' => 'new@example.org', 'family_name' => 'Beispiel', 'password' => 'SafePassword123', 'password_confirmation' => 'SafePassword123'];
        $this->withSession(['registration_started_at' => microtime(true) - 3])->post('/register', $data)->assertRedirect('/dashboard');
        $this->assertDatabaseCount('families', 1);
        $this->assertDatabaseCount('family_users', 1);
        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('SafePassword123', User::first()->password));
    }

    public function test_weak_password_honeypot_and_too_fast_submission_are_rejected(): void
    {
        config(['security.registration_enabled' => true]);
        $data = ['name' => 'Bot', 'email' => 'bot@example.org', 'family_name' => 'Bot', 'password' => 'password', 'password_confirmation' => 'password', 'website' => 'https://spam.example'];
        $this->post('/register', $data)->assertSessionHasErrors(['password', 'website']);
        $data['password'] = $data['password_confirmation'] = 'SafePassword123';
        $data['website'] = '';
        $this->post('/register', $data)->assertSessionHasErrors('form');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_recaptcha_fails_closed_when_unconfigured(): void
    {
        config(['security.registration_enabled' => true, 'services.recaptcha.enabled' => true]);
        $this->withSession(['registration_started_at' => microtime(true) - 3])->post('/register', [
            'name' => 'Test', 'email' => 'test@example.org', 'family_name' => 'Test',
            'password' => 'SafePassword123', 'password_confirmation' => 'SafePassword123',
        ])->assertSessionHasErrors('form');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_csrf_is_enforced_for_mutations(): void
    {
        $this->app->instance('env', 'csrf-test');
        $this->post('/login', ['email' => 'test@example.org', 'password' => 'password'])->assertStatus(419);
    }

    public function test_login_page_does_not_expose_demo_credentials(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('thomas@example.com')->assertDontSee('Test-Login');
    }

    public function test_legacy_bcrypt_password_is_rehashed_to_argon2id_on_login(): void
    {
        if (! in_array('argon2id', password_algos(), true)) {
            $this->markTestSkipped('Argon2id required');
        }
        $user = User::factory()->create(['password' => Hash::make('LegacyPassword123')]);
        config(['hashing.driver' => 'argon2id', 'hashing.argon.verify' => false]);
        Hash::forgetDrivers();
        $this->post('/login', ['email' => $user->email, 'password' => 'LegacyPassword123'])->assertRedirect('/dashboard');
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
        $this->assertTrue(password_verify('LegacyPassword123', $user->fresh()->password));
    }
}
