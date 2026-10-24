<?php

namespace App\Http\Requests;

use App\Services\AuditLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function authenticate(): void
    {
        $key = hash_hmac('sha256', $this->string('email').'|'.$this->ip(), (string) config('app.key'));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Anmeldung vorübergehend gesperrt. Bitte später erneut versuchen.']);
        }
        if (! Auth::attempt($this->safe()->only(['email', 'password']), $this->boolean('remember'))) {
            RateLimiter::hit($key, 900);
            app(AuditLogger::class)->record('login_failed');
            throw ValidationException::withMessages(['email' => 'Anmeldung fehlgeschlagen.']);
        }
        RateLimiter::clear($key);
        app(AuditLogger::class)->record('login');
    }
}
