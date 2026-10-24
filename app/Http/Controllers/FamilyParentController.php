<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteParentRequest;
use App\Http\Requests\UpdateFamilyMemberColorsRequest;
use App\Models\Child;
use App\Models\Family;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class FamilyParentController extends Controller
{
    public function invite(InviteParentRequest $request, Family $family): RedirectResponse
    {
        return DB::transaction(function () use ($request, $family): RedirectResponse {
            $user = User::where('email', mb_strtolower(trim($request->validated('email'))))->lockForUpdate()->first();
            if (! $user) {
                return back()->with('status', 'Es konnte kein berechtigbarer Account gefunden werden.');
            }
            if ($family->users()->whereKey($user->id)->exists()) {
                return back()->with('status', 'Dieser Account ist bereits der Familie zugeordnet.');
            }
            if ($user->families()->exists()) {
                return back()->withErrors(['email' => 'Dieser Login gehört bereits zu einer anderen Familie.']);
            }
            $family->users()->attach($user->id, [
                'role' => 'parent', 'status' => 'active', 'invited_at' => now(), 'accepted_at' => now(),
            ]);
            app(AuditLogger::class)->record('parent_added', $family);

            return back()->with('status', 'Elternteil wurde berechtigt.');
        });
    }

    public function updateColors(UpdateFamilyMemberColorsRequest $request, Family $family): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $family): void {
            foreach ($data['parent_colors'] ?? [] as $parentId => $color) {
                User::whereKey($parentId)->update(['member_color' => $color]);
            }

            foreach ($data['child_colors'] ?? [] as $childId => $color) {
                Child::where('family_id', $family->id)->whereKey($childId)->update(['member_color' => $color]);
            }

            app(AuditLogger::class)->record('member_colors_updated', $family);
        });

        return back()->with('status', 'Farben wurden gespeichert.');
    }
}
