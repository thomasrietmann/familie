<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateImportedEventSuggestionRequest;
use App\Models\ImportedEventSuggestion;
use Illuminate\Http\RedirectResponse;

class ImportedEventSuggestionController extends Controller
{
    public function update(UpdateImportedEventSuggestionRequest $request, ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $suggestion->update($request->data());

        return back()->with('status', 'Vorschlag wurde aktualisiert.');
    }

    public function accept(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);

        $event = $suggestion->family->events()->create([
            'title' => $suggestion->title,
            'description' => $suggestion->description,
            'starts_at' => $suggestion->starts_at,
            'ends_at' => $suggestion->ends_at,
            'all_day' => $suggestion->all_day,
            'location' => $suggestion->location,
            'category' => $suggestion->category ?? 'other',
            'visibility' => 'family',
            'owner_type' => 'family',
            'owner_id' => null,
            'status' => 'planned',
            'source' => 'import',
            'document_import_id' => $suggestion->document_import_id,
        ]);

        $suggestion->update(['status' => 'imported']);
        $suggestion->documentImport->update(['status' => 'imported']);

        return redirect()->route('families.events.show', [$suggestion->family, $event])->with('status', 'Vorschlag wurde importiert.');
    }

    public function reject(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);
        $suggestion->update(['status' => 'rejected']);

        return back()->with('status', 'Vorschlag wurde abgelehnt.');
    }
}
