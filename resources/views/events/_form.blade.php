@csrf
@isset($event->id)
    @method('PUT')
@endisset
<div class="space-y-5">
    <div>
        <label class="text-sm font-medium" for="title">Titel</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="title" name="title" value="{{ old('title', $event->title) }}" required>
    </div>

    <input type="hidden" name="owner_type" value="family">
    <input type="hidden" name="owner_id" value="">

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <label class="text-sm font-medium" for="starts_at">Startdatum / Startzeit</label>
            <input class="mt-1 block w-full rounded-md border-stone-300" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $event->starts_at ? $event->starts_at->format('Y-m-d\TH:i') : '') }}" required>
        </div>
        <div>
            <label class="text-sm font-medium" for="ends_at">Enddatum / Endzeit</label>
            <input class="mt-1 block w-full rounded-md border-stone-300" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $event->ends_at ? $event->ends_at->format('Y-m-d\TH:i') : '') }}">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-stone-700"><input class="rounded border-stone-300" type="checkbox" name="all_day" value="1" @checked(old('all_day', $event->all_day))> Ganztägig</label>

    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label class="text-sm font-medium" for="category">Kategorie</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="category" name="category">
                @foreach(\App\Models\FamilyEvent::CATEGORIES as $category)
                    <option value="{{ $category }}" @selected(old('category', $event->category) === $category)>{{ \App\Models\FamilyEvent::CATEGORY_LABELS[$category] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium" for="visibility">Sichtbarkeit</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="visibility" name="visibility">
                <option value="family" @selected(old('visibility', $event->visibility) === 'family')>Familie</option>
                <option value="parents_only" @selected(old('visibility', $event->visibility) === 'parents_only')>Nur Eltern</option>
            </select>
        </div>
        <div>
            <label class="text-sm font-medium" for="status">Status</label>
            <select class="mt-1 block w-full rounded-md border-stone-300" id="status" name="status">
                <option value="planned" @selected(old('status', $event->status) === 'planned')>Geplant</option>
                <option value="confirmed" @selected(old('status', $event->status) === 'confirmed')>Bestätigt</option>
                <option value="cancelled" @selected(old('status', $event->status) === 'cancelled')>Abgesagt</option>
            </select>
        </div>
    </div>

    <div>
        <label class="text-sm font-medium" for="location">Ort</label>
        <input class="mt-1 block w-full rounded-md border-stone-300" id="location" name="location" value="{{ old('location', $event->location) }}">
    </div>
    <div>
        <label class="text-sm font-medium" for="description">Beschreibung</label>
        <textarea class="mt-1 block w-full rounded-md border-stone-300" id="description" name="description" rows="3">{{ old('description', $event->description) }}</textarea>
    </div>
    <div>
        <label class="text-sm font-medium" for="notes">Notizen</label>
        <textarea class="mt-1 block w-full rounded-md border-stone-300" id="notes" name="notes" rows="3">{{ old('notes', $event->notes) }}</textarea>
    </div>
    <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white">Speichern</button>
</div>
