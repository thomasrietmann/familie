<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\Family;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(config('security.registration_enabled'), 404);
        $request->session()->put('registration_started_at', microtime(true));

        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        [$user, $family] = DB::transaction(function () use ($data): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $family = Family::create([
                'name' => $data['family_name'],
                'owner_user_id' => $user->id,
            ]);

            $family->users()->attach($user->id, [
                'role' => 'owner',
                'status' => 'active',
                'accepted_at' => now(),
            ]);

            return [$user, $family];
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('registration_started_at');
        app(AuditLogger::class)->record('registered', $family);

        return redirect()->route('dashboard')->with('status', $family->name.' wurde erstellt.');
    }
}
