<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class RecaptchaVerifier
{
    public function verify(string $token, string $expectedAction): ?float
    {
        if (! config('services.recaptcha.enabled')) {
            return null;
        }

        $secret = config('services.recaptcha.secret_key');

        if (! is_string($secret) || $secret === '' || $token === '') {
            return null;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.recaptcha.timeout_seconds', 4))
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $result = $response->json();

        if (! is_array($result)
            || ($result['success'] ?? false) !== true
            || ($result['action'] ?? null) !== $expectedAction
            || ! is_numeric($result['score'] ?? null)
            || (float) $result['score'] < (float) config('services.recaptcha.minimum_score', 0.5)
            || ! $this->hasExpectedHostname($result['hostname'] ?? null)
            || ! $this->isFreshChallenge($result['challenge_ts'] ?? null)) {
            return null;
        }

        return (float) $result['score'];
    }

    private function hasExpectedHostname(mixed $hostname): bool
    {
        $expectedHostname = config('services.recaptcha.expected_hostname');

        if (! is_string($expectedHostname) || $expectedHostname === '') {
            return false;
        }

        return is_string($hostname) && hash_equals(strtolower($expectedHostname), strtolower($hostname));
    }

    private function isFreshChallenge(mixed $challengeTimestamp): bool
    {
        if (! is_string($challengeTimestamp) || $challengeTimestamp === '') {
            return false;
        }

        try {
            $challengeAt = CarbonImmutable::parse($challengeTimestamp);
        } catch (Throwable) {
            return false;
        }

        $now = CarbonImmutable::now();
        $maximumAge = (int) config('services.recaptcha.maximum_age_seconds', 120);

        return $challengeAt->lessThanOrEqualTo($now->addSeconds(30))
            && $challengeAt->greaterThanOrEqualTo($now->subSeconds($maximumAge));
    }
}
