<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFamilyRequest;
use App\Http\Requests\UpdateFamilyRequest;
use App\Models\Family;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FamilyController extends Controller
{
    public function index(Request $request): View
    {
        $family = $request->user()->managedFamily();

        return view('families.index', compact('family'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->families()->exists()) {
            return redirect()->route('families.index')->with('status', 'Dieser Login verwaltet bereits eine Familie.');
        }

        return view('families.create');
    }

    public function store(StoreFamilyRequest $request): RedirectResponse
    {
        if ($request->user()->families()->exists()) {
            return redirect()->route('families.index')->with('status', 'Dieser Login verwaltet bereits eine Familie.');
        }

        DB::transaction(function () use ($request): void {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($user->families()->exists(), 409);
            $family = Family::create($request->validated() + ['owner_user_id' => $request->user()->id]);
            $family->users()->attach($request->user()->id, [
                'role' => 'owner',
                'status' => 'active',
                'accepted_at' => now(),
            ]);

        });

        return redirect()->route('families.index')->with('status', 'Familie wurde erstellt.');
    }

    public function show(Family $family): View
    {
        $this->authorize('view', $family);
        $family->load(['children', 'activeParents', 'events' => fn ($query) => $query->where('starts_at', '>=', Carbon::today())->orderBy('starts_at')->limit(8)]);

        return view('families.show', compact('family'));
    }

    public function edit(Family $family): View
    {
        $this->authorize('update', $family);

        return view('families.edit', compact('family'));
    }

    public function update(UpdateFamilyRequest $request, Family $family): RedirectResponse
    {
        DB::transaction(fn () => $family->update($request->validated()));

        return redirect()->route('families.index')->with('status', 'Familie wurde aktualisiert.');
    }

    public function destroy(Request $request, Family $family): RedirectResponse
    {
        $this->authorize('delete', $family);
        $request->validate(['current_password' => ['required', 'string', 'max:1024', 'current_password']]);
        DB::transaction(fn () => $family->delete());

        return redirect()->route('families.index')->with('status', 'Familie wurde gelöscht.');
    }
}
