<?php

namespace App\Http\Requests;

use App\Services\RecaptchaVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('security.registration_enabled') && (! app()->isProduction() || config('services.recaptcha.enabled'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'family_name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:0'],
            'recaptcha_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $started = $this->session()->get('registration_started_at');
            if (! is_numeric($started) || microtime(true) - $started < 2 || microtime(true) - $started > 3600) {
                $validator->errors()->add('form', 'Bitte das Formular neu laden und erneut ausfüllen.');

                return;
            }
            if (config('services.recaptcha.enabled') && app(RecaptchaVerifier::class)->verify($this->string('recaptcha_token')->toString(), 'register') === null) {
                $validator->errors()->add('form', 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte erneut versuchen.');
            }
        });
    }
}
