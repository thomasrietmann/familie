<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateImportedEventSuggestionRequest;
use App\Models\ImportedEventSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ImportedEventSuggestionController extends Controller
{
    public function update(UpdateImportedEventSuggestionRequest $request, ImportedEventSuggestion $suggestion): RedirectResponse
    {
        DB::transaction(function () use ($request, $suggestion): void {
            $locked = ImportedEventSuggestion::whereKey($suggestion->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);
            $locked->update($request->suggestionData());
        });

        return back()->with('status', 'Vorschlag wurde aktualisiert.');
    }

    public function accept(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);
        DB::transaction(fn () => $this->importSuggestion($suggestion));

        return back()->with('status', 'Vorschlag wurde übernommen.');
    }

    public function acceptAll(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);
        DB::transaction(function () use ($suggestion): void {
            $pending = $suggestion->documentImport->suggestions()->where('status', 'pending')->orderBy('id')->lockForUpdate()->get();
            foreach ($pending as $item) {
                $this->importSuggestion($item);
            }
        });

        return back()->with('status', 'Offene Vorschläge wurden übernommen.');
    }

    public function reject(ImportedEventSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('update', $suggestion);
        DB::transaction(function () use ($suggestion): void {
            $locked = ImportedEventSuggestion::whereKey($suggestion->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409);
            $locked->update(['status' => 'rejected']);
        });

        return back()->with('status', 'Vorschlag wurde abgelehnt.');
    }

    private function importSuggestion(ImportedEventSuggestion $suggestion): void
    {
        $suggestion = ImportedEventSuggestion::whereKey($suggestion->id)->lockForUpdate()->firstOrFail();
        if ($suggestion->status === 'imported') {
            return;
        }
        abort_unless($suggestion->status === 'pending', 409);
        $this->authorize('update', $suggestion);
        abort_unless($suggestion->documentImport->family_id === $suggestion->family_id, 409);
        $ownerType = $suggestion->suggested_owner_type ?? 'family';
        $validOwner = match ($ownerType) {
            'family' => true,
            'user' => $suggestion->family->activeParents()->whereKey($suggestion->suggested_owner_id)->exists(),
            'child' => $suggestion->family->children()->whereKey($suggestion->suggested_owner_id)->exists(),
            default => false,
        };
        abort_unless($validOwner, 422, 'Bitte die Personenzuordnung des Vorschlags prüfen.');
        $suggestion->family->events()->create([
            'title' => $suggestion->title, 'description' => $suggestion->description,
            'starts_at' => $suggestion->starts_at, 'ends_at' => $suggestion->ends_at,
            'all_day' => $suggestion->all_day, 'location' => $suggestion->location,
            'category' => $suggestion->category ?? 'other', 'visibility' => 'family',
            'owner_type' => $ownerType, 'owner_id' => $ownerType === 'family' ? null : $suggestion->suggested_owner_id,
            'status' => 'planned', 'source' => 'import', 'document_import_id' => $suggestion->document_import_id,
        ]);
        $suggestion->update(['status' => 'imported']);
        $suggestion->documentImport->update(['status' => 'imported']);
    }
}
