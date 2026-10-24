<?php

namespace Tests\Unit;

use App\Services\RecaptchaVerifier;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.secret_key', 'test-secret');
        config()->set('services.recaptcha.minimum_score', 0.5);
        config()->set('services.recaptcha.expected_hostname', 'leg.example.test');
        config()->set('services.recaptcha.maximum_age_seconds', 120);
    }

    public function test_valid_token_returns_score(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.92,
                'action' => 'registration_submit',
                'challenge_ts' => now()->toIso8601String(),
                'hostname' => 'leg.example.test',
            ]),
        ]);

        $score = app(RecaptchaVerifier::class)->verify('browser-token', 'registration_submit');

        $this->assertSame(0.92, $score);
        Http::assertSent(fn ($request): bool => $request['secret'] === 'test-secret'
            && $request['response'] === 'browser-token');
    }

    public function test_wrong_action_is_rejected(): void
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.99,
                'action' => 'different_action',
                'challenge_ts' => now()->toIso8601String(),
                'hostname' => 'leg.example.test',
            ]),
        ]);

        $this->assertNull(app(RecaptchaVerifier::class)->verify('browser-token', 'registration_submit'));
    }

    public function test_low_score_stale_timestamp_and_wrong_hostname_are_rejected(): void
    {
        foreach ([
            ['score' => 0.2, 'challenge_ts' => now()->toIso8601String(), 'hostname' => 'leg.example.test'],
            ['score' => 0.9, 'challenge_ts' => now()->subMinutes(5)->toIso8601String(), 'hostname' => 'leg.example.test'],
            ['score' => 0.9, 'challenge_ts' => now()->toIso8601String(), 'hostname' => 'other.example.test'],
        ] as $responseData) {
            Http::fake([
                'https://www.google.com/recaptcha/api/siteverify' => Http::response(array_merge([
                    'success' => true,
                    'action' => 'registration_submit',
                ], $responseData)),
            ]);

            $this->assertNull(app(RecaptchaVerifier::class)->verify('browser-token', 'registration_submit'));
        }
    }

    public function test_disabled_recaptcha_does_not_make_external_request(): void
    {
        config()->set('services.recaptcha.enabled', false);
        Http::fake();

        $this->assertNull(app(RecaptchaVerifier::class)->verify('browser-token', 'registration_submit'));
        Http::assertNothingSent();
    }
}
