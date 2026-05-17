<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteParentRequest;
use App\Http\Requests\UpdateFamilyMemberColorsRequest;
use App\Models\Child;
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

        if ($user->hasManagedFamily() && ! $family->users()->whereKey($user->id)->exists()) {
            return back()->withErrors(['email' => 'Dieser Login verwaltet bereits eine andere Familie.']);
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

    public function updateColors(UpdateFamilyMemberColorsRequest $request, Family $family): RedirectResponse
    {
        $data = $request->validated();

        foreach ($data['parent_colors'] ?? [] as $parentId => $color) {
            User::whereKey($parentId)->update(['member_color' => $color]);
        }

        foreach ($data['child_colors'] ?? [] as $childId => $color) {
            Child::where('family_id', $family->id)->whereKey($childId)->update(['member_color' => $color]);
        }

        return back()->with('status', 'Farben wurden gespeichert.');
    }
}
