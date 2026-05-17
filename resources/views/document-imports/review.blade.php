<x-layouts.app title="Import prüfen">
    @php($family = $documentImport->family)
    @php($firstPendingSuggestion = $documentImport->suggestions->firstWhere('status', 'pending'))
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Import-Vorschläge prüfen</h1>
            <p class="mt-2 text-sm text-stone-600">{{ $documentImport->title }} · {{ $family->name }}</p>
        </div>
        @if($firstPendingSuggestion)
            <form method="POST" action="{{ route('imported-event-suggestions.accept-all', $firstPendingSuggestion) }}">
                @csrf
                <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" type="submit">
                    Alle offenen übernehmen
                </button>
            </form>
        @endif
    </div>
    <div class="space-y-4">
        @forelse($documentImport->suggestions as $suggestion)
            <form method="POST" action="{{ route('imported-event-suggestions.update', $suggestion) }}" class="rounded-lg border border-stone-200 bg-white p-5 shadow-sm">
                @csrf
                @method('PUT')
                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="text-sm font-medium">Titel</label>
                        <input class="mt-1 block w-full rounded-md border-stone-300" name="title" value="{{ old('title', $suggestion->title) }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Start</label>
                        <input class="mt-1 block w-full rounded-md border-stone-300" name="starts_at" type="datetime-local" value="{{ old('starts_at', $suggestion->starts_at->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Ende</label>
                        <input class="mt-1 block w-full rounded-md border-stone-300" name="ends_at" type="datetime-local" value="{{ old('ends_at', $suggestion->ends_at ? $suggestion->ends_at->format('Y-m-d\TH:i') : '') }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Ort</label>
                        <input class="mt-1 block w-full rounded-md border-stone-300" name="location" value="{{ old('location', $suggestion->location) }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium">Kategorie</label>
                        <select class="mt-1 block w-full rounded-md border-stone-300" name="category"><option value="">Keine</option>@foreach(\App\Models\FamilyEvent::CATEGORIES as $category)<option value="{{ $category }}" @selected($suggestion->category === $category)>{{ \App\Models\FamilyEvent::CATEGORY_LABELS[$category] }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Für wen?</label>
                        <select class="mt-1 block w-full rounded-md border-stone-300" name="owner_type">
                            <option value="family" @selected($suggestion->suggested_owner_type === 'family')>Ganze Familie</option>
                            <option value="user" @selected($suggestion->suggested_owner_type === 'user')>Elternteil</option>
                            <option value="child" @selected($suggestion->suggested_owner_type === 'child')>Kind</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium">Person</label>
                        <select class="mt-1 block w-full rounded-md border-stone-300" name="owner_id">
                            <option value="">Ganze Familie / keine Person</option>
                            @foreach($family->activeParents as $parent)<option value="{{ $parent->id }}" @selected($suggestion->suggested_owner_type === 'user' && $suggestion->suggested_owner_id === $parent->id)>{{ $parent->name }}</option>@endforeach
                            @foreach($family->children as $child)<option value="{{ $child->id }}" @selected($suggestion->suggested_owner_type === 'child' && $suggestion->suggested_owner_id === $child->id)>{{ $child->displayName() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="lg:col-span-4">
                        <label class="text-sm font-medium">Beschreibung</label>
                        <textarea class="mt-1 block w-full rounded-md border-stone-300" name="description" rows="2">{{ old('description', $suggestion->description) }}</textarea>
                    </div>
                </div>
                <input type="hidden" name="confidence" value="{{ $suggestion->confidence }}">
                <label class="mt-4 flex items-center gap-2 text-sm"><input class="rounded border-stone-300" type="checkbox" name="all_day" value="1" @checked($suggestion->all_day)> Ganztägig</label>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <x-badge>{{ $suggestion->statusLabel() }}</x-badge>
                    <x-badge tone="blue">Confidence {{ $suggestion->confidence }}</x-badge>
                    <button class="rounded-md border border-stone-300 px-4 py-2 text-sm font-medium">Bearbeiten speichern</button>
                    <button form="accept-{{ $suggestion->id }}" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white" type="submit">Übernehmen</button>
                    <button form="reject-{{ $suggestion->id }}" class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700" type="submit">Ablehnen</button>
                </div>
            </form>
            <form id="accept-{{ $suggestion->id }}" method="POST" action="{{ route('imported-event-suggestions.accept', $suggestion) }}">@csrf</form>
            <form id="reject-{{ $suggestion->id }}" method="POST" action="{{ route('imported-event-suggestions.reject', $suggestion) }}">@csrf</form>
        @empty
            <div class="rounded-lg border border-stone-200 bg-white p-6 text-sm text-stone-600 shadow-sm">
                Keine offenen Import-Vorschläge mehr vorhanden.
            </div>
        @endforelse
    </div>
</x-layouts.app>
