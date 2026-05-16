<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteParentRequest;
use App\Models\Family;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class FamilyParentController extends Controller
{
    public function invite(InviteParentRequest $request, Family $family): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user) {
            return back()->with('status', 'Für dieses MVP wurde die Einladung vorgemerkt. Sobald ein User mit dieser E-Mail existiert, kann er berechtigt werden.');
        }

        $family->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'parent',
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
            ],
        ]);

        return back()->with('status', 'Elternteil wurde berechtigt.');
    }
}
