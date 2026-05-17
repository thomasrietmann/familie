<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateImportedEventSuggestionRequest;
use App\Models\FamilyEvent;
use App\Models\ImportedEventSuggestion;
use Illuminate\Http\RedirectResponse;

class ImportedEventSuggestionController extends Controller
{
    public function update(UpdateImportedEventSuggestionRequest $request, ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $suggestion->update($request->suggestionData());

        return back()->with('status', 'Vorschlag wurde aktualisiert.');
    }

    public function accept(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);

        $this->importSuggestion($suggestion);
        $suggestion->documentImport->update(['status' => 'imported']);

        return back()->with('status', 'Vorschlag wurde importiert.');
    }

    public function acceptAll(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);

        $documentImport = $suggestion->documentImport;
        $pendingSuggestions = $documentImport->suggestions()
            ->where('status', 'pending')
            ->orderBy('starts_at')
            ->get();

        foreach ($pendingSuggestions as $pendingSuggestion) {
            $this->importSuggestion($pendingSuggestion);
        }

        if ($pendingSuggestions->isNotEmpty()) {
            $documentImport->update(['status' => 'imported']);
        }

        return back()->with('status', $pendingSuggestions->count().' Vorschläge wurden importiert.');
    }

    public function reject(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);
        $suggestion->update(['status' => 'rejected']);

        return back()->with('status', 'Vorschlag wurde abgelehnt.');
    }

    private function importSuggestion(ImportedEventSuggestion $suggestion): FamilyEvent
    {
        $event = $suggestion->family->events()->create([
            'title' => $suggestion->title,
            'description' => $suggestion->description,
            'starts_at' => $suggestion->starts_at,
            'ends_at' => $suggestion->ends_at,
            'all_day' => $suggestion->all_day,
            'location' => $suggestion->location,
            'category' => $suggestion->category ?? 'other',
            'visibility' => 'family',
            'owner_type' => $suggestion->suggested_owner_type ?? 'family',
            'owner_id' => $suggestion->suggested_owner_type === 'family' ? null : $suggestion->suggested_owner_id,
            'status' => 'planned',
            'source' => 'import',
            'document_import_id' => $suggestion->document_import_id,
        ]);

        $suggestion->update(['status' => 'imported']);

        return $event;
    }
}
