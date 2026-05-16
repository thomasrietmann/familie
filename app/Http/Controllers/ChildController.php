<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChildRequest;
use App\Http\Requests\UpdateChildRequest;
use App\Models\Child;
use App\Models\Family;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChildController extends Controller
{
    public function index(Family $family): View
    {
        $this->authorize('view', $family);
        $children = $family->children()->withCount('events')->orderBy('first_name')->get();

        return view('children.index', compact('family', 'children'));
    }

    public function create(Family $family): View
    {
        $this->authorize('update', $family);

        return view('children.create', compact('family'));
    }

    public function store(StoreChildRequest $request, Family $family): RedirectResponse
    {
        $family->children()->create($request->validated());

        return redirect()->route('families.children.index', $family)->with('status', 'Kind wurde erstellt.');
    }

    public function edit(Family $family, Child $child): View
    {
        $this->authorize('update', $child);

        return view('children.edit', compact('family', 'child'));
    }

    public function update(UpdateChildRequest $request, Family $family, Child $child): RedirectResponse
    {
        $child->update($request->validated());

        return redirect()->route('families.children.index', $family)->with('status', 'Kind wurde aktualisiert.');
    }

    public function destroy(Family $family, Child $child): RedirectResponse
    {
        $this->authorize('delete', $child);
        $child->delete();

        return back()->with('status', 'Kind wurde gelöscht.');
    }
}
