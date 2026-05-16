<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'family_name' => ['required', 'string', 'max:255'],
        ]);

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

        return redirect()->route('dashboard')->with('status', $family->name.' wurde erstellt.');
    }
}
