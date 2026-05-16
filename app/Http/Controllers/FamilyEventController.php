<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFamilyEventRequest;
use App\Http\Requests\UpdateFamilyEventRequest;
use App\Models\Family;
use App\Models\FamilyEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FamilyEventController extends Controller
{
    public function index(Request $request, Family $family): View
    {
        $this->authorize('view', $family);

        $events = $family->events()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->category))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->when($request->filled('visibility'), fn ($query) => $query->where('visibility', $request->visibility))
            ->when($request->filled('from'), fn ($query) => $query->where('starts_at', '>=', Carbon::parse($request->from)->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('starts_at', '<=', Carbon::parse($request->to)->endOfDay()))
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        $family->load(['children', 'activeParents']);

        return view('events.index', compact('family', 'events'));
    }

    public function create(Family $family): View
    {
        $this->authorize('update', $family);
        $family->load(['children', 'activeParents']);
        $event = new FamilyEvent(['category' => 'other', 'visibility' => 'family', 'status' => 'planned', 'owner_type' => 'family', 'owner_id' => null]);

        return view('events.create', compact('family', 'event'));
    }

    public function store(StoreFamilyEventRequest $request, Family $family): RedirectResponse
    {
        $event = $family->events()->create($request->data() + ['source' => 'manual']);

        return redirect()->route('families.events.show', [$family, $event])->with('status', 'Termin wurde erstellt.');
    }

    public function show(Family $family, FamilyEvent $event): View
    {
        $this->authorize('view', $event);

        return view('events.show', compact('family', 'event'));
    }

    public function edit(Family $family, FamilyEvent $event): View
    {
        $this->authorize('update', $event);
        $family->load(['children', 'activeParents']);

        return view('events.edit', compact('family', 'event'));
    }

    public function update(UpdateFamilyEventRequest $request, Family $family, FamilyEvent $event): RedirectResponse
    {
        $event->update($request->data());

        return redirect()->route('families.events.show', [$family, $event])->with('status', 'Termin wurde aktualisiert.');
    }

    public function destroy(Family $family, FamilyEvent $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->delete();

        return redirect()->route('families.events.index', $family)->with('status', 'Termin wurde gelöscht.');
    }
}
