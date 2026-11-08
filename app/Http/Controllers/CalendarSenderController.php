<?php

namespace App\Http\Controllers;

use App\Mail\VerifyCalendarSender;
use App\Models\CalendarMailReceipt;
use App\Models\CalendarSenderAddress;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CalendarSenderController extends Controller
{
    public function index(Request $request): View
    {
        $family = $request->user()->managedFamily();
        abort_unless($family, 403);

        return view('calendar-mail.index', [
            'family' => $family,
            'addresses' => CalendarSenderAddress::where('user_id', $request->user()->id)->where('family_id', $family->id)->orderBy('email')->get(),
            'receipts' => CalendarMailReceipt::where('user_id', $request->user()->id)->where('family_id', $family->id)->latest()->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $family = $request->user()->managedFamily();
        abort_unless($family, 403);
        $request->merge(['email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : null]);
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'regex:/^[\x21-\x7e]+$/'],
            'current_password' => ['required', 'current_password'],
            'consent' => ['accepted'],
        ]);
        if ($data['email'] === strtolower(config('calendar_mail.address'))) {
            throw ValidationException::withMessages(['email' => 'Die Empfangsadresse kann nicht als Absender verwendet werden.']);
        }
        $code = (string) random_int(10000000, 99999999);
        $address = DB::transaction(function () use ($request, $family, $data, $code) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = CalendarSenderAddress::where('email', $data['email'])->lockForUpdate()->first();
            if ($existing || CalendarSenderAddress::where('user_id', $request->user()->id)->count() >= 10) {
                throw ValidationException::withMessages(['email' => 'Diese Adresse kann nicht hinzugefügt werden. Prüfe deine Einträge (maximal 10 Adressen).']);
            }

            return CalendarSenderAddress::create([
                'user_id' => $request->user()->id, 'family_id' => $family->id, 'email' => $data['email'],
                'consented_at' => now(), 'verification_hash' => $this->codeHash($code), 'verification_expires_at' => now()->addMinutes(30),
            ]);
        });
        $this->sendCode($address, $code);
        app(AuditLogger::class)->record('calendar_sender_added', $address);

        return back()->with('status', 'Bestätigungscode wurde gesendet. Die Adresse ist erst nach Bestätigung aktiv.');
    }

    public function resend(Request $request, CalendarSenderAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        abort_if($address->verified_at !== null, 409);
        $code = (string) random_int(10000000, 99999999);
        $address->update(['verification_hash' => $this->codeHash($code), 'verification_expires_at' => now()->addMinutes(30), 'verification_attempts' => 0]);
        $this->sendCode($address, $code);

        return back()->with('status', 'Ein neuer Bestätigungscode wurde gesendet.');
    }

    public function verify(Request $request, CalendarSenderAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{8}$/']]);
        $verified = DB::transaction(function () use ($address, $data) {
            $address = CalendarSenderAddress::whereKey($address->id)->lockForUpdate()->firstOrFail();
            if ($address->verified_at || ! $address->verification_expires_at?->isFuture() || $address->verification_attempts >= 5) {
                return false;
            }
            $address->increment('verification_attempts');
            if (! hash_equals((string) $address->verification_hash, $this->codeHash($data['code']))) {
                return false;
            }
            $address->update(['verified_at' => now(), 'verification_hash' => null, 'verification_expires_at' => null]);
            app(AuditLogger::class)->record('calendar_sender_verified', $address);

            return true;
        });
        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'Code ungültig, abgelaufen oder gesperrt. Fordere bei Bedarf einen neuen Code an.']);
        }

        return back()->with('status', 'Absenderadresse bestätigt. Einladungen werden jetzt deiner Familie zugeordnet.');
    }

    public function destroy(Request $request, CalendarSenderAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        DB::transaction(function () use ($address): void {
            $address = CalendarSenderAddress::whereKey($address->id)->lockForUpdate()->firstOrFail();
            app(AuditLogger::class)->record('calendar_sender_removed', $address);
            $address->delete();
        });

        return back()->with('status', 'Absenderadresse entfernt. Bereits importierte Termine bleiben erhalten.');
    }

    private function authorizeAddress(Request $request, CalendarSenderAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id && $request->user()->activeFamilies()->whereKey($address->family_id)->exists(), 404);
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    private function sendCode(CalendarSenderAddress $address, string $code): void
    {
        // Never write verification codes to a log mailer, including during misconfiguration.
        if (config('mail.default') !== 'smtp' && ! (app()->environment('testing') && config('mail.default') === 'array')) {
            throw ValidationException::withMessages(['email' => 'Der E-Mail-Versand ist noch nicht eingerichtet. Die Adresse bleibt inaktiv.']);
        }
        try {
            Mail::to($address->email)->send(new VerifyCalendarSender($code));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['email' => 'Der Bestätigungscode konnte nicht gesendet werden. Bitte später erneut anfordern.']);
        }
    }
}
